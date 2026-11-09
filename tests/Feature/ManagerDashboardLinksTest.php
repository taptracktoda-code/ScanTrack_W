<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\QrCode;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDashboardLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_six_cards_link_to_the_records_counted_in_the_managers_organization(): void
    {
        // Manila is already November 9 while the server's UTC date is November 8.
        $this->travelTo(CarbonImmutable::parse('2026-11-08 17:00:00', 'UTC'));
        [$organization, $manager] = $this->organization('North');
        [$foreign] = $this->organization('South');
        $active = $this->driver($organization, 'Active driver', 'Active');
        $inactive = $this->driver($organization, 'Inactive driver', 'Inactive');
        $otherActive = $this->driver($organization, 'Another active driver', 'Active');
        $foreignDriver = $this->driver($foreign, 'Foreign driver', 'Active');
        $activeQr = QrCode::create(['driver_id' => $active->id, 'qr_token' => 'north-active', 'status' => 'Active']);
        $inactiveDriverQr = QrCode::create(['driver_id' => $inactive->id, 'qr_token' => 'north-inactive-driver', 'status' => 'Active']);
        QrCode::create(['driver_id' => $otherActive->id, 'qr_token' => 'north-inactive', 'status' => 'Inactive']);
        QrCode::create(['driver_id' => $foreignDriver->id, 'qr_token' => 'south-active', 'status' => 'Active']);
        $vehicle = Vehicle::create(['organization_id' => $organization->id, 'name' => 'North vehicle', 'plate_number' => 'NORTH-1', 'status' => 'Active']);
        Vehicle::create(['organization_id' => $foreign->id, 'name' => 'South vehicle', 'plate_number' => 'SOUTH-1', 'status' => 'Active']);

        $entryStart = $this->log($active, 'ENTRY', '2026-11-08 16:00:00');
        $entryEnd = $this->log($active, 'ENTRY', '2026-11-09 15:59:59');
        $entryFallback = $this->log($inactive, 'ENTRY', null, '2026-11-08 18:00:00');
        $exit = $this->log($active, 'EXIT', '2026-11-08 17:00:00');
        $this->log($active, 'ENTRY', '2026-11-08 15:59:59');
        $this->log($active, 'ENTRY', '2026-11-09 16:00:00');
        $this->log($active, 'EXIT', null, '2026-11-08 15:59:59');
        $this->log($active, 'EXIT', '2026-11-09 16:00:00');
        $this->log($foreignDriver, 'ENTRY', '2026-11-08 17:00:00');
        $this->log($foreignDriver, 'EXIT', '2026-11-08 17:00:00');

        $dashboard = $this->actingAs($manager)->get(route('manager.dashboard'))->assertOk();
        $expected = [
            'Drivers' => ['manager.drivers', [], 'drivers', [$active->id, $inactive->id, $otherActive->id]],
            'Active Drivers' => ['manager.drivers', ['status' => 'Active'], 'drivers', [$active->id, $otherActive->id]],
            'Vehicles' => ['manager.vehicles', [], 'vehicles', [$vehicle->id]],
            'Active QR Codes' => ['manager.qr.management', ['status' => 'Active'], 'qrCodes', [$activeQr->id, $inactiveDriverQr->id]],
            'Entries Today' => ['manager.attendance', ['attendance_type' => 'ENTRY', 'start_date' => '2026-11-09', 'end_date' => '2026-11-09'], 'records', [$entryStart->id, $entryEnd->id, $entryFallback->id]],
            'Exits Today' => ['manager.attendance', ['attendance_type' => 'EXIT', 'start_date' => '2026-11-09', 'end_date' => '2026-11-09'], 'records', [$exit->id]],
        ];
        $stats = collect($dashboard->viewData('stats'))->keyBy('label');
        $this->assertCount(6, $stats);
        $this->assertSame(6, substr_count($dashboard->getContent(), 'manager-summary-link'));
        foreach ($expected as $label => [$route, $filters, $variable, $ids]) {
            $url = route($route, $filters);
            $this->assertSame($url, $stats[$label]['url']);
            $this->assertSame(count($ids), $stats[$label]['value']);
            $dashboard->assertSee('href="'.e($url).'" class="card dashboard-card manager-summary-link', false);
            $response = $this->get($url)->assertOk()->assertDontSee('Foreign driver')->assertDontSee('South vehicle');
            $records = $response->viewData($variable);
            $this->assertEqualsCanonicalizing($ids, $records->pluck('id')->all(), $label);
            $this->assertSame($stats[$label]['value'], $records->total(), $label);
        }
        $this->get($stats['Entries Today']['url'])->assertSee('Nov 9, 2026 00:00:00')->assertSee('Recorded at (Manila)');
        $this->get($stats['Active Drivers']['url'])->assertSee('value="Active" selected', false);
        $this->get($stats['Active QR Codes']['url'])->assertSee('value="Active" selected', false);
    }

    public function test_zero_count_cards_remain_links_and_destinations_have_empty_states(): void
    {
        [, $manager] = $this->organization('Empty');
        $dashboard = $this->actingAs($manager)->get(route('manager.dashboard'))->assertOk();
        $this->assertSame(6, substr_count($dashboard->getContent(), 'manager-summary-link'));
        foreach ($dashboard->viewData('stats') as $stat) {
            $this->assertSame(0, $stat['value']);
            $this->get($stat['url'])->assertOk()->assertSee(match ($stat['label']) {
                'Drivers' => 'No drivers are assigned to this organization yet.',
                'Active Drivers' => 'No drivers match these filters.',
                'Vehicles' => 'No vehicles are assigned to this organization.',
                'Active QR Codes' => 'No QR records match this status.',
                default => 'No attendance records match these filters.',
            });
        }
    }

    public function test_status_filters_combine_with_search_and_persist_when_paginating(): void
    {
        [$organization, $manager] = $this->organization('Filters');
        $inactive = $this->driver($organization, 'Match inactive', 'Inactive');
        $activeIds = [];
        for ($i = 0; $i < 16; $i++) {
            $driver = $this->driver($organization, 'Match '.$i, 'Active');
            $activeIds[] = $driver->id;
            QrCode::create(['driver_id' => $driver->id, 'qr_token' => 'active-'.$i, 'status' => 'Active']);
        }
        QrCode::create(['driver_id' => $inactive->id, 'qr_token' => 'inactive-code', 'status' => 'Inactive']);
        $this->actingAs($manager)->get(route('manager.drivers', ['status' => 'Active', 'search' => 'Match']))
            ->assertOk()->assertViewHas('drivers', fn ($drivers) => $drivers->total() === 16)
            ->assertDontSee('Match inactive')->assertSee('status=Active&amp;search=Match&amp;page=2', false);
        $this->get(route('manager.drivers', ['status' => 'Active', 'search' => 'Missing']))->assertOk()->assertSee('No drivers match these filters.');
        $this->get(route('manager.qr.management', ['status' => 'Active']))->assertOk()
            ->assertViewHas('qrCodes', fn ($codes) => $codes->total() === 16)->assertDontSee('inactive-code')
            ->assertSee('status=Active&amp;page=2', false);
        $this->get(route('manager.qr.management', ['status' => 'Active', 'page' => 2]))->assertOk()
            ->assertViewHas('qrCodes', fn ($codes) => $codes->count() === 1 && in_array($codes->first()->driver_id, $activeIds));
        $this->get(route('manager.drivers', ['status' => 'invalid']))->assertSessionHasErrors('status');
        $this->get(route('manager.qr.management', ['status' => 'invalid']))->assertSessionHasErrors('status');
        $this->get(route('manager.attendance', ['start_date' => '2026-11-09', 'end_date' => '2026-11-08']))->assertSessionHasErrors('end_date');
    }

    public function test_dashboard_date_changes_at_manila_midnight_and_attendance_range_supports_multiple_days(): void
    {
        [$organization, $manager] = $this->organization('Midnight');
        $driver = $this->driver($organization, 'Midnight driver', 'Active');
        $this->log($driver, 'ENTRY', '2026-11-09 15:59:59');
        $this->log($driver, 'ENTRY', '2026-11-09 16:00:00');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 15:59:59', 'UTC'));
        $before = $this->actingAs($manager)->get(route('manager.dashboard'))->assertOk();
        $entries = collect($before->viewData('stats'))->firstWhere('label', 'Entries Today');
        $this->assertSame(1, $entries['value']);
        $this->assertStringContainsString('start_date=2026-11-09', $entries['url']);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 16:00:00', 'UTC'));
        $after = $this->get(route('manager.dashboard'))->assertOk();
        $entries = collect($after->viewData('stats'))->firstWhere('label', 'Entries Today');
        $this->assertSame(1, $entries['value']);
        $this->assertStringContainsString('start_date=2026-11-10', $entries['url']);
        $this->get($entries['url'])->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1);
        $this->get(route('manager.attendance', ['start_date' => '2026-11-09', 'end_date' => '2026-11-10']))
            ->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 2);
    }

    private function organization(string $name): array
    {
        $organization = TodaOrganization::create(['name' => $name, 'barangay_travel_area' => $name, 'status' => 'Active']);
        $manager = User::factory()->create(['role' => 'MANAGER', 'status' => 'Active', 'organization_id' => $organization->id, 'must_change_password' => false]);
        return [$organization, $manager];
    }

    private function driver(TodaOrganization $organization, string $name, string $status): Driver
    {
        return Driver::create(['organization_id' => $organization->id, 'name' => $name, 'status' => $status,
            'license_number' => fake()->unique()->uuid(), 'contact_number' => '09170000000']);
    }

    private function log(Driver $driver, string $type, ?string $scannedAt, string $createdAt = '2026-11-01 00:00:00'): DriverLog
    {
        return DriverLog::create(['driver_id' => $driver->id, 'attendance_type' => $type, 'scanned_at' => $scannedAt,
            'scan_method' => 'QR', 'created_at' => $createdAt]);
    }
}
