<?php

namespace Tests\Feature;

use App\Models\AcademicItem;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserProfileAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[TestWith(['teacher'])]
    #[TestWith(['student'])]
    public function test_admin_can_view_profiles_and_all_uploaded_resources(string $role): void
    {
        $subject = User::factory()->create(['role' => $role, 'profile' => ['bio' => 'Academic biography', 'skills' => 'Laravel']]);
        $item = AcademicItem::factory()->for($subject)->create(['title' => 'Private resource', 'visibility' => 'private']);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('users.index'))->assertOk()->assertSee(route('users.show', $subject));
        $this->get(route('users.show', $subject))->assertOk()->assertSee('Academic biography')->assertSee('Laravel')->assertSee('Private resource')->assertSee(route('items.show', $item));
    }

    public function test_teacher_can_view_assigned_student_profile_and_private_course_uploads(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('repository/student.txt', 'Student work');
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create(['profile' => ['bio' => 'Student biography']]);
        $course = Course::factory()->create();
        $course->teachers()->attach($teacher);
        $course->students()->attach($student);
        $item = AcademicItem::factory()->for($student)->for($course)->create(['title' => 'Shared course work', 'visibility' => 'private', 'file_path' => 'repository/student.txt', 'file_name' => 'student.txt']);
        AcademicItem::factory()->for($student)->create(['title' => 'Other course work', 'visibility' => 'private']);
        $this->actingAs($teacher);

        $this->get(route('users.show', $student))->assertOk()->assertSee('Student biography')->assertSee('Shared course work')->assertDontSee('Other course work');
        $this->get(route('items.show', $item))->assertOk();
        $this->get(route('items.download', $item))->assertDownload('student.txt');
    }

    #[TestWith(['teacher'])]
    #[TestWith(['admin'])]
    #[TestWith(['student'])]
    public function test_teacher_cannot_view_other_teachers_admins_or_unrelated_students(string $role): void
    {
        $subject = User::factory()->create(['role' => $role]);

        $this->actingAs(User::factory()->teacher()->create())->get(route('users.show', $subject))->assertForbidden();
    }

    public function test_students_cannot_view_another_users_staff_profile(): void
    {
        $subject = User::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('users.show', $subject))->assertForbidden();
    }

    public function test_profile_content_is_escaped(): void
    {
        $subject = User::factory()->create(['profile' => ['bio' => '<script>alert(1)</script>']]);

        $this->actingAs(User::factory()->admin()->create())->get(route('users.show', $subject))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
