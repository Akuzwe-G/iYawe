<?php

namespace Tests\Feature;

use App\Models\CardSwap;
use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnalyticsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function member(Company $company, string $role): User
    {
        CompanyAccess::provision($company);
        $user = User::factory()->create();
        $company->users()->attach($user->id, ['active' => true]);
        setPermissionsTeamId($company->id);
        $user->assignRole(Role::where('company_id', $company->id)->where('name', $role)->firstOrFail());
        setPermissionsTeamId(null);

        return $user;
    }

    public function test_all_dashboard_periods_and_chart_groupings_match_isolated_records(): void
    {
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 18)->startOfDay());
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $admin = $this->member($a, 'Company Administrator');
        $field = $this->member($a, 'Field Staff');
        $other = $this->member($b, 'Company Administrator');
        $location = Location::create(['company_id' => $a->id, 'name' => 'Fictional station', 'active' => true]);
        foreach ([[0, 'completed', 'lost', $field], [6, 'pending_processing', 'damaged', $admin], [7, 'processing_unsuccessful', 'faulty', $field], [29, 'completed', 'other', $admin], [89, 'draft', 'lost', $admin], [90, 'completed', 'lost', $field], [300, 'completed', 'lost', $field]] as [$days,$status,$reason,$owner]) {
            CardSwap::factory()->create(['company_id' => $a->id, 'created_by' => $owner->id, 'swap_date' => today()->subDays($days), 'status' => $status, 'reason' => $reason, 'location_id' => $location->id]);
        }
        CardSwap::factory()->count(2)->create(['company_id' => $b->id, 'created_by' => $other->id, 'swap_date' => today(), 'status' => 'completed', 'reason' => 'lost']);
        foreach (['7' => [2, 1, 1, 0], '30' => [4, 2, 1, 1], '90' => [5, 2, 1, 1], 'year' => [6, 3, 1, 1], 'custom' => [3, 1, 1, 1]] as $period => $expected) {
            $period = (string) $period;
            $query = http_build_query(['period' => $period] + ($period === 'custom' ? ['from' => '2026-10-11', 'to' => '2026-10-18'] : []));
            $response = $this->actingAs($admin)->get('/dashboard?'.$query)->assertOk();
            $metrics = $response->viewData('metrics');
            foreach ($expected as $i => $value) {
                $this->assertSame($value, $metrics[$i][1], (string) $period);
            }
            $charts = collect($response->viewData('charts'));
            $activity = $charts->firstWhere('title', 'Swap activity');
            $this->assertSame($expected[0], array_sum($activity['datasets'][0]['data']));
            $this->assertSame($expected[1], array_sum($activity['datasets'][1]['data']));
            $this->assertSame($expected[0], array_sum($charts->firstWhere('title', 'Status distribution')['datasets'][0]['data']));
            $this->assertSame($expected[0], array_sum($charts->firstWhere('title', 'Swaps by staff')['datasets'][0]['data']));
            $this->assertSame($expected[0], array_sum($charts->firstWhere('title', 'Replacement reasons')['datasets'][0]['data']));
            $this->assertTrue($charts->firstWhere('title', 'Replacement reasons')['horizontal']);
            $this->assertCount($period === '7' ? 7 : ($period === '30' ? 30 : ($period === '90' ? 90 : ($period === 'custom' ? 8 : 291))), $activity['labels']);
        }
        $fieldMetrics = $this->actingAs($field)->get('/dashboard?period=30')->assertOk()->viewData('metrics');
        $this->assertSame(2, $fieldMetrics[0][1]);
        $system = User::factory()->create(['is_system_admin' => true]);
        $data = $this->actingAs($system)->get('/dashboard?period=7')->assertOk();
        $this->assertSame(4, $data->viewData('metrics')[4][1]);
        $this->assertNotEmpty($data->viewData('charts'));
        $this->actingAs($admin)->get('/dashboard?period=custom&from=2020-01-01&to=2026-10-18')->assertSessionHasErrors('from');
    }

    public function test_empty_dashboard_has_no_fabricated_activity_and_report_filters_remain_scoped(): void
    {
        $this->withoutVite();
        $company = Company::factory()->create();
        $user = $this->member($company, 'Company Administrator');
        $response = $this->actingAs($user)->get('/dashboard?period=7')->assertOk()->assertSee('No activity in this period');
        $this->assertSame(0, $response->viewData('metrics')[0][1]);
        CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'swap_date' => today(), 'status' => 'completed', 'reason' => 'lost']);
        CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'swap_date' => today()->subDays(20), 'status' => 'pending_processing', 'reason' => 'damaged']);
        $this->get('/reports?from='.today()->format('Y-m-d').'&to='.today()->format('Y-m-d').'&status=completed&reason=lost')->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && $summary['completed'] === 1);
    }
}
