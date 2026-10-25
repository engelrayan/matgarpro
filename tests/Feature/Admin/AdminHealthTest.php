<?php

namespace Tests\Feature\Admin;

use App\Jobs\QueueHeartbeat;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Support\Heartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.whatsapp.waha' => ['url' => 'http://waha.test', 'api_key' => 'k', 'webhook_secret' => 's', 'callback_url' => 'https://x.test', 'engine' => 'GOWS']]);
    }

    /** @return array<string,array<string,mixed>> the page's checks, by key */
    private function checks(?Admin $admin = null): array
    {
        $page = $this->actingAs($admin ?? Admin::factory()->create(), 'admin')
            ->get('http://localhost/admin/health')
            ->assertOk()
            ->viewData('page');

        return collect($page['props']['checks'])->keyBy('key')->all();
    }

    public function test_a_quiet_scheduler_and_workers_show_as_stopped_with_the_fix(): void
    {
        Http::fake(['waha.test/*' => Http::response([])]);

        $checks = $this->checks();

        $this->assertSame('fail', $checks['scheduler']['status']);
        $this->assertStringContainsString('matgarpro-scheduler.timer', $checks['scheduler']['fix']);
        $this->assertSame('fail', $checks['queue:default']['status']);
        $this->assertStringContainsString('matgarpro-worker', $checks['queue:default']['fix']);
    }

    public function test_a_fresh_heartbeat_shows_as_running(): void
    {
        Http::fake(['waha.test/*' => Http::response([])]);
        Heartbeat::beat(Heartbeat::SCHEDULER);
        (new QueueHeartbeat('default'))->handle();
        (new QueueHeartbeat((string) config('ai.queue')))->handle();

        $checks = $this->checks();

        $this->assertSame('ok', $checks['scheduler']['status']);
        $this->assertSame('ok', $checks['queue:default']['status']);
        $this->assertSame('ok', $checks['queue:' . config('ai.queue')]['status']);
    }

    public function test_a_heartbeat_older_than_three_minutes_is_a_stopped_worker(): void
    {
        Http::fake(['waha.test/*' => Http::response([])]);
        $this->travelTo(now()->subMinutes(10), fn () => (new QueueHeartbeat('default'))->handle());

        $this->assertSame('fail', $this->checks()['queue:default']['status']);
    }

    public function test_the_waha_check_counts_linked_numbers_and_notices_when_it_is_down(): void
    {
        Http::fake(['waha.test/*' => Http::response([['name' => 'store-1', 'status' => 'WORKING'], ['name' => 'store-2', 'status' => 'FAILED']])]);
        $up = $this->checks()['waha'];
        $this->assertSame('ok', $up['status']);
        $this->assertStringContainsString('1 من 2', $up['detail']);

        Http::fake(['waha.test/*' => fn () => throw new ConnectionException('refused')]);
        $this->assertSame('fail', $this->checks()['waha']['status']);
    }

    public function test_debug_mode_on_a_production_server_is_flagged(): void
    {
        Http::fake(['waha.test/*' => Http::response([])]);
        config(['app.debug' => true]);
        $this->app['env'] = 'production';

        $this->assertSame('fail', $this->checks()['debug']['status']);
    }

    private function failedJob(): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            // A real queued job's payload: queue:retry unserialises it.
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => 'App\\Jobs\\SendWhatsappOrderConfirmation',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'maxTries' => null,
                'timeout' => null,
                'data' => [
                    'commandName' => QueueHeartbeat::class,
                    'command' => serialize(new QueueHeartbeat('default')),
                ],
            ]),
            'exception' => "RuntimeException: WAHA refused\n#0 stack",
            'failed_at' => now(),
        ]);

        return $uuid;
    }

    public function test_a_failed_job_is_listed_and_can_be_retried(): void
    {
        Http::fake(['waha.test/*' => Http::response([])]);
        $uuid = $this->failedJob();

        $page = $this->actingAs(Admin::factory()->create(), 'admin')->get('http://localhost/admin/health')->viewData('page');
        $this->assertSame('SendWhatsappOrderConfirmation', $page['props']['failed'][0]['job']);
        $this->assertSame('RuntimeException: WAHA refused', $page['props']['failed'][0]['error']);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->post("http://localhost/admin/health/failed/{$uuid}/retry")
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertTrue(AdminActivityLog::where('action', 'job.retried')->exists());
    }

    public function test_only_a_super_admin_can_drop_a_failed_job(): void
    {
        $uuid = $this->failedJob();

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->delete("http://localhost/admin/health/failed/{$uuid}")
            ->assertNotFound();
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);

        $this->actingAs(Admin::factory()->super()->create(), 'admin')
            ->delete("http://localhost/admin/health/failed/{$uuid}")
            ->assertRedirect();
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }
}
