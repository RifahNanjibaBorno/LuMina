<?php

namespace Tests\Feature;

use App\Models\AcademicItem;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PortfolioApplicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_workspace_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_registration_creates_a_student_and_ignores_privilege_fields(): void
    {
        $this->post(route('register.store'), ['name' => 'Student Example', 'email' => 'student@example.test', 'university_id' => '123456', 'password' => 'StrongPassword1!', 'password_confirmation' => 'StrongPassword1!', 'role' => 'admin', 'is_active' => false])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'student@example.test', 'role' => 'student', 'is_active' => true]);
        $this->assertAuthenticated();
        $this->assertTrue(Hash::check('StrongPassword1!', User::where('email', 'student@example.test')->first()->password));
    }

    public function test_login_accepts_university_id_for_the_matching_role(): void
    {
        $user = User::factory()->create(['university_id' => '123456']);
        $this->post(route('login.store'), ['identity' => '123456', 'password' => 'password', 'role' => 'student'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_role_cannot_authenticate(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['identity' => $user->email, 'password' => 'password', 'role' => 'admin'])->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_disabled_account_cannot_access_existing_session(): void
    {
        $user = User::factory()->disabled()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[TestWith(['student'])]
    #[TestWith(['teacher'])]
    #[TestWith(['admin'])]
    public function test_each_role_has_a_renderable_dashboard(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee(ucfirst($role).' Dashboard');
    }

    public function test_student_cannot_access_admin_user_management(): void
    {
        $this->actingAs(User::factory()->create())->get(route('users.index'))->assertForbidden();
    }

    public function test_teacher_cannot_change_another_users_role(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $this->actingAs($teacher)->put(route('users.update', $student), ['name' => $student->name, 'email' => $student->email, 'role' => 'admin', 'is_active' => 1])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $student->id, 'role' => 'student']);
    }

    public function test_admin_can_disable_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->actingAs($admin)->put(route('users.update', $student), ['name' => $student->name, 'email' => $student->email, 'role' => 'student', 'is_active' => 0])->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => false]);
    }

    public function test_admin_cannot_disable_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'is_active' => 0])->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_private_repository_entry_is_hidden_from_unrelated_users(): void
    {
        $item = AcademicItem::factory()->create(['visibility' => 'private']);
        $this->actingAs(User::factory()->create())->get(route('items.show', $item))->assertNotFound();
    }

    public function test_course_members_can_view_course_uploads(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $course->students()->attach($student);
        $item = AcademicItem::factory()->for($course)->create(['visibility' => 'course']);
        $this->actingAs($student)->get(route('items.show', $item))->assertOk()->assertSee($item->title);
    }

    #[TestWith(['owner', true, true])]
    #[TestWith(['admin', true, true])]
    #[TestWith(['teacher', false, false])]
    #[TestWith(['student', false, false])]
    public function test_private_item_permission_matrix(string $role, bool $canView, bool $canUpdate): void
    {
        $item = AcademicItem::factory()->create(['visibility' => 'private']);
        $viewer = $role === 'owner' ? $item->user : User::factory()->create(['role' => $role]);
        $this->assertSame($canView, Gate::forUser($viewer)->allows('view', $item));
        $this->assertSame($canUpdate, Gate::forUser($viewer)->allows('update', $item));
    }

    public function test_upload_stores_a_private_attachment_and_owned_record(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $course->students()->attach($student);
        $file = UploadedFile::fake()->createWithContent('report.txt', 'Course project report');
        $this->actingAs($student)->post(route('items.store'), ['title' => 'Course report', 'description' => 'Project documentation', 'type' => 'report', 'course_id' => $course->id, 'visibility' => 'course', 'file' => $file, 'user_id' => 9999])->assertSessionHasNoErrors()->assertRedirect();
        $item = AcademicItem::where('title', 'Course report')->firstOrFail();
        $this->assertSame($student->id, $item->user_id);
        Storage::disk('local')->assertExists($item->file_path);
    }

    public function test_upload_to_unassigned_course_is_rejected(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->create())->post(route('items.store'), ['title' => 'Project', 'description' => 'Description', 'type' => 'project', 'course_id' => $course->id, 'visibility' => 'public'])->assertSessionHasErrors('course_id');
        $this->assertDatabaseCount('academic_items', 0);
    }

    public function test_executable_attachment_is_rejected(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $course->students()->attach($student);
        $this->actingAs($student)->post(route('items.store'), ['title' => 'Unsafe attachment', 'description' => 'Description', 'type' => 'project', 'course_id' => $course->id, 'visibility' => 'private', 'file' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('academic_items', 0);
    }

    public function test_unauthorized_download_is_forbidden(): void
    {
        $item = AcademicItem::factory()->create(['visibility' => 'private', 'file_path' => 'repository/private.txt']);
        $this->actingAs(User::factory()->create())->get(route('items.download', $item))->assertForbidden();
        $this->assertSame(0, $item->fresh()->downloads);
    }

    public function test_authorized_download_returns_original_filename_and_records_activity(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('repository/report.txt', 'Academic report');
        $item = AcademicItem::factory()->create(['visibility' => 'private', 'file_path' => 'repository/report.txt', 'file_name' => 'Course_Report.txt']);
        $this->actingAs($item->user)->get(route('items.download', $item))->assertDownload('Course_Report.txt');
        $this->assertSame(1, $item->fresh()->downloads);
        $this->assertDatabaseHas('activities', ['user_id' => $item->user_id, 'action' => 'File downloaded']);
        Storage::disk('local')->assertExists('repository/report.txt');
    }

    public function test_foreign_owner_cannot_delete_an_upload(): void
    {
        $item = AcademicItem::factory()->create();
        $this->actingAs(User::factory()->create())->delete(route('items.destroy', $item))->assertForbidden();
        $this->assertDatabaseHas('academic_items', ['id' => $item->id]);
    }

    public function test_owner_deletion_removes_attachment_and_metadata(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('repository/report.txt', 'Report');
        $item = AcademicItem::factory()->create(['file_path' => 'repository/report.txt']);
        $this->actingAs($item->user)->delete(route('items.destroy', $item))->assertRedirect(route('repository.index'));
        $this->assertDatabaseMissing('academic_items', ['id' => $item->id]);
        Storage::disk('local')->assertMissing('repository/report.txt');
    }

    public function test_repository_filters_preserve_visibility_boundaries(): void
    {
        $course = Course::factory()->create(['semester' => 'Autumn 2026']);
        AcademicItem::factory()->for($course)->create(['title' => 'Public Laravel Project', 'visibility' => 'public', 'technology' => 'Laravel']);
        AcademicItem::factory()->for($course)->create(['title' => 'Private Laravel Project', 'visibility' => 'private', 'technology' => 'Laravel']);
        AcademicItem::factory()->for($course)->create(['title' => 'Public Python Project', 'visibility' => 'public', 'technology' => 'Python']);
        $this->get(route('repository.index', ['technology' => 'Laravel', 'semester' => 'Autumn 2026']))->assertOk()->assertSee('Public Laravel Project')->assertDontSee('Private Laravel Project')->assertDontSee('Public Python Project');
    }

    public function test_rendered_upload_content_is_escaped(): void
    {
        $item = AcademicItem::factory()->create(['visibility' => 'public', 'description' => '<script>alert("xss")</script>']);
        $this->get(route('items.show', $item))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_private_portfolio_is_not_publicly_accessible(): void
    {
        $user = User::factory()->create(['portfolio_slug' => 'private-student', 'portfolio_public' => false]);
        $this->get(route('portfolio.public', $user->portfolio_slug))->assertNotFound();
    }

    public function test_public_portfolio_only_renders_selected_sections_and_projects(): void
    {
        $user = User::factory()->create(['portfolio_slug' => 'public-student', 'portfolio_public' => true, 'visible_sections' => ['skills', 'projects'], 'profile' => ['skills' => 'Laravel', 'experience' => 'Hidden work history']]);
        AcademicItem::factory()->for($user)->create(['title' => 'Featured work', 'is_featured' => true]);
        AcademicItem::factory()->for($user)->create(['title' => 'Unselected work', 'is_featured' => false]);
        $this->get(route('portfolio.public', $user->portfolio_slug))->assertOk()->assertSee('Featured work')->assertSee('Laravel')->assertDontSee('Unselected work')->assertDontSee('Hidden work history');
        $this->assertSame(1, $user->fresh()->portfolio_views);
    }

    public function test_portfolio_settings_cannot_feature_another_students_project(): void
    {
        $user = User::factory()->create();
        $foreign = AcademicItem::factory()->create();
        $this->actingAs($user)->put(route('portfolio.update'), ['portfolio_slug' => 'my-profile', 'featured' => [$foreign->id]])->assertSessionHasErrors('featured.0');
        $this->assertFalse($foreign->fresh()->is_featured);
    }

    public function test_portfolio_save_updates_sharing_sections_and_project_selection(): void
    {
        $user = User::factory()->create();
        $project = AcademicItem::factory()->for($user)->create();
        $this->actingAs($user)->put(route('portfolio.update'), ['portfolio_slug' => 'student-portfolio', 'portfolio_public' => 1, 'visible_sections' => ['skills', 'projects'], 'featured' => [$project->id]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'portfolio_slug' => 'student-portfolio', 'portfolio_public' => true]);
        $this->assertTrue($project->fresh()->is_featured);
        $this->assertSame(['skills', 'projects'], $user->fresh()->visible_sections);
    }

    public function test_profile_save_ignores_role_and_clears_resume_draft(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['resume_draft' => ['skills' => 'Python']])->put(route('profile.update'), ['name' => 'Updated Student', 'email' => $user->email, 'skills' => 'Python, Laravel', 'role' => 'admin'])->assertSessionHasNoErrors()->assertSessionMissing('resume_draft');
        $this->assertSame('student', $user->fresh()->role);
        $this->assertSame('Python, Laravel', $user->fresh()->profile['skills']);
    }

    public function test_resume_import_creates_a_draft_without_overwriting_profile(): void
    {
        Storage::fake('local');
        Process::preventStrayProcesses();
        Process::fake(['*resume_parser.py*' => Process::result(output: json_encode(['text_found' => true, 'name' => 'Imported Student', 'skills' => 'Python']))]);
        $user = User::factory()->create(['profile' => ['skills' => 'Laravel']]);
        $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');
        $this->actingAs($user)->post(route('portfolio.import'), ['resume' => $file])->assertRedirect(route('profile.edit'))->assertSessionHas('resume_draft.skills', 'Python');
        $this->assertSame('Laravel', $user->fresh()->profile['skills']);
        Storage::disk('local')->assertExists($user->fresh()->resume_path);
        Process::assertRan(fn ($process): bool => in_array(resource_path('python/resume_parser.py'), $process->command, true));
    }

    public function test_unreadable_resume_returns_a_validation_error(): void
    {
        Process::preventStrayProcesses();
        Process::fake(['*resume_parser.py*' => Process::result(output: json_encode(['text_found' => false]))]);
        $this->actingAs(User::factory()->create())->post(route('portfolio.import'), ['resume' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf')])->assertSessionHasErrors('resume');
        Process::assertRan(fn ($process): bool => in_array(resource_path('python/resume_parser.py'), $process->command, true));
    }

    public function test_teacher_can_only_list_students_from_assigned_courses(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create();
        $course->teachers()->attach($teacher);
        $member = User::factory()->create(['name' => 'Assigned Student']);
        User::factory()->create(['name' => 'Unassigned Student']);
        $course->students()->attach($member);
        $this->actingAs($teacher)->get(route('users.index'))->assertOk()->assertSee('Assigned Student')->assertDontSee('Unassigned Student');
    }

    public function test_resume_process_failure_returns_a_form_error(): void
    {
        Process::preventStrayProcesses();
        Process::fake(['*resume_parser.py*' => function (): never {
            throw new \RuntimeException('Process unavailable');
        }]);
        $this->actingAs(User::factory()->create())->post(route('portfolio.import'), ['resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf')])->assertSessionHasErrors('resume');
    }

    public function test_failed_pdf_extraction_does_not_replace_the_resume(): void
    {
        Process::preventStrayProcesses();
        Process::fake(['*resume_parser.py*' => Process::result(exitCode: 1)]);
        $user = User::factory()->create(['resume_name' => 'existing.pdf', 'resume_path' => 'resumes/existing.pdf']);
        $this->actingAs($user)->post(route('portfolio.import'), ['resume' => UploadedFile::fake()->create('invalid.pdf', 100, 'application/pdf')])->assertSessionHasErrors('resume');
        $this->assertSame('existing.pdf', $user->fresh()->resume_name);
        Process::assertRan(fn ($process): bool => in_array(resource_path('python/resume_parser.py'), $process->command, true));
    }

    public function test_admin_can_assign_student_course_membership(): void
    {
        $admin = User::factory()->admin()->create();
        $course = Course::factory()->create();
        $student = User::factory()->create();
        $this->actingAs($admin)->put(route('courses.enroll', $course), ['students' => [$student->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('course_user', ['course_id' => $course->id, 'user_id' => $student->id]);
    }

    public function test_assigned_teacher_can_enroll_students(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create();
        $course->teachers()->attach($teacher);
        $student = User::factory()->create();
        $this->actingAs($teacher)->put(route('courses.enroll', $course), ['students' => [$student->id]])->assertSessionHasNoErrors();
        $this->assertTrue($course->students->contains($student));
    }

    public function test_admin_can_create_course_and_assign_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('courses.store'), ['title' => 'Software Project Design', 'semester' => 'Autumn 2026', 'teachers' => [$teacher->id], 'status' => 'active'])->assertSessionHasNoErrors()->assertRedirect();
        $course = Course::where('title', 'Software Project Design')->firstOrFail();
        $this->assertTrue($course->teachers->contains($teacher));
    }

    #[TestWith(['profile.edit'])]
    #[TestWith(['portfolio.edit'])]
    #[TestWith(['projects.index'])]
    #[TestWith(['items.create'])]
    public function test_student_editor_pages_render(string $route): void
    {
        $this->actingAs(User::factory()->create())->get(route($route))->assertOk();
    }
}
