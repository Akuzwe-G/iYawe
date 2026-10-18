<?php
namespace Tests\Feature;
use App\Models\CardSwap;
use App\Models\Company;
use App\Models\User;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
class SwapWorkflowSmokeTest extends TestCase {
 use RefreshDatabase;
 private function staff(): array {
  $this->withoutVite(); Storage::fake('local');
  $company=Company::factory()->create(); CompanyAccess::provision($company);
  $user=User::factory()->create(); $company->users()->attach($user->id,['active'=>true]);
  setPermissionsTeamId($company->id); $user->assignRole(Role::where('company_id',$company->id)->where('name','Company Administrator')->firstOrFail()); setPermissionsTeamId(null);
  $this->actingAs($user); return [$company,$user];
 }
 private function signature(): string {
  $image=imagecreatetruecolor(320,180); imagefill($image,0,0,imagecolorallocate($image,255,255,255)); $ink=imagecolorallocate($image,23,43,77); imagesetthickness($image,3);
  imageline($image,30,120,95,45,$ink); imageline($image,95,45,110,130,$ink); imageline($image,110,130,180,60,$ink); imageline($image,180,60,260,125,$ink);
  ob_start(); imagepng($image); $png=ob_get_clean(); imagedestroy($image); return 'data:image/png;base64,'.base64_encode($png);
 }
 private function draft(): CardSwap {
  $this->post('/swaps',['submission_key'=>(string)Str::uuid()])->assertRedirect();
  $swap=CardSwap::firstOrFail();
  $this->post('/swaps/'.$swap->id.'/steps/1',['revision'=>0,'swap_date'=>today()->format('Y-m-d'),'commuter_name'=>'Sample Commuter','phone'=>'+250788123456','national_id'=>'TEST-12345'])->assertSessionHasNoErrors();
  $this->post('/swaps/'.$swap->id.'/steps/2',['revision'=>1,'old_card'=>'OLD-12345','new_card'=>'NEW-98765','old_balance'=>'1000.25','new_balance'=>'1000.25','reason'=>'damaged'])->assertSessionHasNoErrors();
  return $swap->fresh();
 }
 public function test_completed_workflow_private_files_pdf_reports_and_dashboards(): void {
  [$company,$user]=$this->staff();
  $this->get('/swaps/create')->assertOk(); $this->get('/consents')->assertOk();
  $this->post('/consents',['language'=>'en','version'=>'test-en-v1','declaration'=>config('consent.en.declaration'),'receipt'=>config('consent.en.receipt'),'approved'=>1])->assertSessionHasNoErrors();
  $swap=$this->draft(); $this->get('/swaps/'.$swap->id.'/continue')->assertOk();
  $this->post('/swaps/'.$swap->id.'/steps/3',['revision'=>2,'consent_version_id'=>1,'consent_name'=>'Sample Commuter','acknowledged'=>1,'signature'=>$this->signature()])->assertSessionHasNoErrors();
  $this->post('/swaps/'.$swap->id.'/steps/4',['revision'=>3,'outcome'=>'completed','physical_confirmation'=>1,'remarks'=>'Physical verification complete','signature'=>$this->signature()])->assertSessionHasNoErrors();
  $this->post('/swaps/'.$swap->id.'/steps/5',['revision'=>4,'receipt_acknowledged'=>1,'signature'=>$this->signature()])->assertSessionHasNoErrors();
  $swap=$swap->fresh(); $this->assertSame('completed',$swap->status); $this->assertCount(3,$swap->signatures);
  $this->assertStringNotContainsString('TEST-12345',$swap->getRawOriginal('national_id'));
  $this->assertStringNotContainsString('PNG',Storage::disk('local')->get($swap->signatures->first()->path));
  $this->get('/swaps/'.$swap->id)->assertOk()->assertSee('Sample Commuter');
  $this->get('/swaps/'.$swap->id.'/signatures/'.$swap->signatures->first()->id)->assertOk()->assertHeader('Content-Type','image/png');
  $this->get('/swaps/'.$swap->id.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
  $this->assertNotNull($swap->fresh()->pdf_path);
  $this->get('/records')->assertOk();
  $this->post('/records/search',['search_by'=>'national_id','search'=>'TEST-12345'])->assertRedirect('/records?searching=1');
  $this->get('/records?searching=1')->assertOk()->assertSee($swap->reference);
  $this->get('/reports')->assertOk(); $this->get('/reports/export')->assertOk()->assertDontSee('TEST-12345');
  $this->get('/dashboard')->assertOk()->assertSee('Swap activity');
  $this->post('/swaps/'.$swap->id.'/steps/5',['revision'=>5,'receipt_acknowledged'=>1,'signature'=>$this->signature()])->assertForbidden();
 }
 public function test_no_receipt_before_physical_completion_and_no_cross_company_access(): void {
  [$company,$user]=$this->staff(); $swap=$this->draft();
  $this->post('/swaps/'.$swap->id.'/steps/5',['revision'=>2,'receipt_acknowledged'=>1,'signature'=>$this->signature()])->assertConflict();
  $other=Company::factory()->create(); CompanyAccess::provision($other);
  $otherUser=User::factory()->create(); $other->users()->attach($otherUser->id,['active'=>true]);
  setPermissionsTeamId($other->id); $otherUser->assignRole(Role::where('company_id',$other->id)->where('name','Company Administrator')->firstOrFail()); setPermissionsTeamId(null);
  $this->actingAs($otherUser)->withSession(['company_id'=>$other->id])->get('/swaps/'.$swap->id)->assertNotFound();
  $this->get('/swaps/'.$swap->id.'/pdf')->assertNotFound();
 }
 public function test_unapproved_consent_and_blank_signature_are_blocked(): void {
  $this->staff(); $swap=$this->draft();
  $this->get('/swaps/'.$swap->id.'/continue')->assertOk()->assertSee('No approved consent');
  $this->post('/swaps/'.$swap->id.'/steps/3',['revision'=>2,'consent_version_id'=>999,'consent_name'=>'Sample Commuter','acknowledged'=>1,'signature'=>'data:image/png;base64,'])->assertSessionHasErrors('consent_version_id');
 }
}
