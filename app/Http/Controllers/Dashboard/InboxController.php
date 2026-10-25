<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\RunsAiGenerations;
use App\Jobs\RunAiGeneration;
use App\Models\AiGeneration;
use App\Models\Order;
use App\Models\Store;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationNote;
use App\Models\WhatsappMessage;
use App\Models\WhatsappQuickReply;
use App\Services\AI\AiFeature;
use App\Services\Whatsapp\ConfirmsWithButtons;
use App\Services\Whatsapp\DriverFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The WhatsApp inbox: every conversation on the store's number, answered
 * from the dashboard instead of the phone the number is linked to.
 *
 * The page loads once through Inertia; after that it polls the two JSON
 * endpoints — the list and the open conversation — every few seconds, so a
 * reply shows up without the merchant reloading anything.
 */
class InboxController extends Controller
{
    use RunsAiGenerations;

    /** The newest of a conversation, which is what anyone scrolls to anyway. */
    private const THREAD_LENGTH = 150;

    public function __construct(private readonly DriverFactory $drivers) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->currentStore();
        $link = $store->whatsappIntegration;

        return Inertia::render('inbox/Index', [
            'conversations' => $this->list($request, $store),
            'selected' => $request->integer('c') ?: null,
            'channel' => [
                'linked' => (bool) $link,
                'can_reply' => $this->canReply($store),
                // Why the composer is closed, when it is.
                'reason' => match (true) {
                    ! $link => 'اربط رقم واتساب الأول من إعدادات الواتساب.',
                    $link->isPlatform() => 'رسايل التأكيد بتطلع من رقم متجر برو، والرد عليها من هنا محتاج تربط رقمك.',
                    ! $link->isSessionGateway() => 'الرد من هنا شغال على الرقم المربوط بالكود بس.',
                    ! $link->canSend() => 'الرقم مش متوصّل دلوقتي — اربطه تاني من إعدادات الواتساب.',
                    default => null,
                },
            ],
            'quickReplies' => $this->quickReplies($store),
            'suggestedTags' => WhatsappConversation::SUGGESTED_TAGS,
            'currency' => $store->currency,
        ]);
    }

    /** The list, for the page's polling. */
    public function conversations(Request $request): JsonResponse
    {
        return response()->json(['conversations' => $this->list($request, $request->user()->currentStore())]);
    }

    /** One conversation, opened — which also marks it read. */
    public function show(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        if ($conversation->unread_count > 0 && ! $request->boolean('peek')) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }

        return response()->json($this->thread($conversation));
    }

    public function reply(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $store = $this->authorizeConversation($request, $conversation);

        $data = $request->validate(
            ['body' => ['required', 'string', 'max:4000']],
            ['body.required' => 'اكتب الرسالة الأول.', 'body.max' => 'الرسالة طويلة أوي.'],
        );

        abort_unless($this->canReply($store), 422, 'الرد من هنا مش متاح على الرقم ده.');

        $driver = $this->drivers->make($store->whatsappIntegration);
        $body = trim($data['body']);

        $result = $driver instanceof ConfirmsWithButtons
            ? $driver->sendText($conversation->phone, $body)
            : $driver->send($conversation->phone, $body);

        $message = WhatsappMessage::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'direction' => WhatsappMessage::DIRECTION_OUT,
            'kind' => WhatsappMessage::KIND_TEXT,
            'phone' => $conversation->phone,
            'body' => $body,
            'meta' => ['source' => 'inbox'],
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->ok ? 'sent' : 'failed',
            'error' => $result->error,
        ]);

        return response()->json(['message' => $this->presentMessage($message->load('user'))], $result->ok ? 201 : 422);
    }

    public function markUnread(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->forceFill(['unread_count' => max(1, $conversation->unread_count)])->save();

        return response()->json(['ok' => true]);
    }

    public function tags(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'tags' => ['present', 'array', 'max:8'],
            'tags.*' => ['string', 'max:30'],
        ]);

        $tags = collect($data['tags'])->map(fn ($t) => trim($t))->filter()->unique()->values()->all();
        $conversation->forceFill(['tags' => $tags ?: null])->save();

        return response()->json(['tags' => $tags]);
    }

    public function storeNote(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']], ['body.required' => 'اكتب الملاحظة الأول.']);

        $note = $conversation->notes()->create(['user_id' => $request->user()->id, 'body' => trim($data['body'])]);

        return response()->json(['note' => $this->presentNote($note->load('user'))], 201);
    }

    public function destroyNote(Request $request, WhatsappConversationNote $note): JsonResponse
    {
        $this->authorizeConversation($request, $note->conversation);
        $note->delete();

        return response()->json(['ok' => true]);
    }

    /** Confirm or cancel one of the customer's orders without leaving the chat. */
    public function orderStatus(Request $request, WhatsappConversation $conversation, Order $order): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        abort_unless($conversation->orders()->visible()->whereKey($order->id)->exists(), 404);

        $data = $request->validate(['status' => ['required', Rule::in(Order::STATUSES)]]);
        $order->update($data);

        return response()->json(['orders' => $this->orders($conversation)]);
    }

    public function storeQuickReply(Request $request): JsonResponse
    {
        $store = $request->user()->currentStore();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:2000'],
        ], ['title.required' => 'سمّي الرد.', 'body.required' => 'اكتب نص الرد.']);

        WhatsappQuickReply::create(['store_id' => $store->id, ...array_map('trim', $data)]);

        return response()->json(['quickReplies' => $this->quickReplies($store)], 201);
    }

    public function destroyQuickReply(Request $request, WhatsappQuickReply $reply): JsonResponse
    {
        $store = $request->user()->currentStore();
        abort_unless((int) $reply->store_id === (int) $store->id, 404);
        $reply->delete();

        return response()->json(['quickReplies' => $this->quickReplies($store)]);
    }

    /** «ملخّص الذكاء» and «اقترح رد» — one reading, both answers. */
    public function assist(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $store = $this->authorizeConversation($request, $conversation);

        if ($refusal = $this->refuseToStart($store, AiFeature::InboxAssistant, 'لسه بنقرا محادثة. استنى ثواني.')) {
            return $refusal;
        }

        $generation = AiGeneration::create([
            'store_id' => $store->id,
            'user_id' => $request->user()->id,
            'feature' => AiFeature::InboxAssistant->value,
            'status' => AiGeneration::STATUS_PENDING,
            'input' => ['conversation_id' => $conversation->id],
        ]);

        RunAiGeneration::dispatch($generation->id);

        return response()->json($this->presentGeneration($generation->refresh()), 202);
    }

    public function assistance(Request $request, WhatsappConversation $conversation, AiGeneration $generation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json($this->presentGeneration(
            $this->ownedGeneration($request, $generation, AiFeature::InboxAssistant),
        ));
    }

    // ─────────────────────────────────────────────────────────────────────

    private function authorizeConversation(Request $request, ?WhatsappConversation $conversation): Store
    {
        $store = $request->user()->currentStore();
        abort_unless($conversation && (int) $conversation->store_id === (int) $store->id, 404);

        return $store;
    }

    /**
     * Replies go out on the store's own number only. The platform's number
     * is shared by every shop, and a Cloud API number can only open with an
     * approved template, so neither can carry free text typed here.
     */
    private function canReply(Store $store): bool
    {
        $link = $store->whatsappIntegration;

        return $link !== null && $link->isSessionGateway() && $link->canSend();
    }

    /** @return array<int,array<string,mixed>> */
    private function list(Request $request, Store $store): array
    {
        $search = trim((string) $request->query('q', ''));

        return WhatsappConversation::query()
            ->where('store_id', $store->id)
            ->whereNotNull('last_message_at')
            ->when($request->query('filter') === 'unread', fn ($q) => $q->where('unread_count', '>', 0))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', '%' . ltrim(preg_replace('/\D/', '', $search) ?: $search, '0') . '%')))
            ->latest('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn (WhatsappConversation $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => self::localPhone($c->phone),
                'preview' => $c->last_preview,
                'last_direction' => $c->last_direction,
                'last_at' => $c->last_message_at?->toIso8601String(),
                'unread' => $c->unread_count,
                'tags' => $c->tags ?? [],
            ])
            ->all();
    }

    /** @return array<string,mixed> */
    private function thread(WhatsappConversation $conversation): array
    {
        $messages = $conversation->messages()->with('user:id,name', 'order:id,number')
            ->latest('id')->limit(self::THREAD_LENGTH)->get()->reverse()->values();

        return [
            'conversation' => [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'phone' => self::localPhone($conversation->phone),
                'wa_link' => 'https://wa.me/' . $conversation->phone,
                'tags' => $conversation->tags ?? [],
                'summary' => $conversation->summary,
                'summary_at' => $conversation->summary_at?->toIso8601String(),
            ],
            'messages' => $messages->map(fn (WhatsappMessage $m) => $this->presentMessage($m))->all(),
            'notes' => $conversation->notes()->with('user:id,name')->oldest('id')->get()
                ->map(fn (WhatsappConversationNote $n) => $this->presentNote($n))->all(),
            'orders' => $this->orders($conversation),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function orders(WhatsappConversation $conversation): array
    {
        return $conversation->orders()->visible()->with('items')->latest('id')->limit(10)->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'status' => $o->status,
                'status_label' => $o->statusLabel(),
                'total' => number_format((float) $o->total, 2, '.', ''),
                'items' => $o->items->map(fn ($i) => $i->quantity . '× ' . $i->name . ($i->variant_label ? " ({$i->variant_label})" : ''))->all(),
                'created_at' => $o->created_at->toIso8601String(),
            ])
            ->all();
    }

    /** @return array<string,mixed> */
    private function presentMessage(WhatsappMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'kind' => $m->kind,
            'body' => $m->body,
            'options' => data_get($m->meta, 'options'),
            'status' => $m->status,
            'error' => $m->error,
            'at' => $m->created_at->toIso8601String(),
            // Who on the shop's side wrote it: a teammate, the phone itself,
            // or the platform on its own (confirmations, receipts).
            'by' => match (true) {
                $m->direction === WhatsappMessage::DIRECTION_IN => null,
                $m->user !== null => $m->user->name,
                data_get($m->meta, 'source') === 'phone' => 'من الموبايل',
                default => 'تلقائي',
            },
            'order_number' => $m->order?->number,
        ];
    }

    /** @return array<string,mixed> */
    private function presentNote(WhatsappConversationNote $n): array
    {
        return [
            'id' => $n->id,
            'body' => $n->body,
            'by' => $n->user?->name,
            'at' => $n->created_at->toIso8601String(),
        ];
    }

    /** @return array<int,array{id:int,title:string,body:string}> */
    private function quickReplies(Store $store): array
    {
        return WhatsappQuickReply::where('store_id', $store->id)->orderBy('title')->get(['id', 'title', 'body'])->toArray();
    }

    /** `201006262330` → `01006262330`; anything not Egyptian stays international. */
    public static function localPhone(string $phone): string
    {
        return str_starts_with($phone, '20') && strlen($phone) === 12 ? '0' . substr($phone, 2) : '+' . $phone;
    }
}
