<?php

namespace Tests\Feature;

use App\Models\AcademicItem;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_create_course_with_multiple_teachers_and_students(): void
    {
        $teachers = User::factory()->teacher()->count(2)->create();
        $students = User::factory()->count(2)->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('courses.create'))->assertOk()->assertSee('Course name')->assertSee($teachers[1]->name)->assertDontSee('name="code"', false);
        $this->post(route('courses.store'), $this->courseData() + ['teachers' => $teachers->modelKeys(), 'students' => $students->modelKeys()])->assertSessionHasNoErrors()->assertRedirect();

        $course = Course::where('title', 'Computer Science')->firstOrFail();
        $this->assertSame('active', $course->status);
        $this->assertGreaterThan(0, $course->id);
        $this->assertNull($course->code);
        $this->assertCount(2, $course->teachers);
        $this->assertCount(2, $course->students);
        $this->get(route('courses.edit', $course))->assertOk()->assertDontSee('Assign to course')->assertDontSee('data-assignment-role', false);

        $this->get(route('courses.show', $course))->assertOk()->assertSee($teachers[0]->name)->assertSee($teachers[1]->name);
    }

    public function test_admin_can_replace_assignments_and_set_inactive_status(): void
    {
        $course = Course::factory()->create();
        $oldTeacher = User::factory()->teacher()->create();
        $newTeacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $course->teachers()->attach($oldTeacher);
        $course->students()->attach($student);

        $this->actingAs(User::factory()->admin()->create())->put(route('courses.update', $course), array_replace($this->courseData(), ['status' => 'inactive', 'teachers' => [$newTeacher->id], 'students' => []]))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('inactive', $course->fresh()->status);
        $this->assertSame([$newTeacher->id], $course->teachers()->pluck('users.id')->all());
        $this->assertCount(0, $course->students);
    }

    public function test_student_can_join_multiple_courses_without_duplicate_enrollment(): void
    {
        $student = User::factory()->create();
        $courses = Course::factory()->count(2)->create();
        $this->actingAs(User::factory()->admin()->create());

        foreach ($courses as $course) {
            $this->put(route('courses.enroll', $course), ['students' => [$student->id]])->assertSessionHasNoErrors();
            $this->put(route('courses.enroll', $course), ['students' => [$student->id]])->assertSessionHasNoErrors();
        }

        $this->assertCount(2, $student->courses);
        $this->assertDatabaseCount('course_user', 2);
        $this->put(route('courses.enroll', $courses[0]), ['students' => [$student->id, $student->id]])->assertSessionHasErrors('students.0');
        $this->assertDatabaseCount('course_user', 2);
    }

    public function test_database_prevents_duplicate_course_enrollment(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $course->students()->attach($student);

        $this->expectException(QueryException::class);
        $course->students()->attach($student);
    }

    public function test_second_assigned_teacher_has_course_and_resource_access(): void
    {
        $teachers = User::factory()->teacher()->count(2)->create();
        $course = Course::factory()->create(['title' => 'Shared teaching course']);
        $course->teachers()->attach($teachers->modelKeys());
        $student = User::factory()->create(['name' => 'Assigned Student']);
        $course->students()->attach($student);
        $item = AcademicItem::factory()->for($course)->create(['visibility' => 'course']);

        $this->actingAs($teachers[1]);
        $this->get(route('courses.index'))->assertOk()->assertSee('Shared teaching course');
        $this->get(route('courses.show', $course))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertSee('Shared teaching course');
        $this->get(route('users.index'))->assertOk()->assertSee('Assigned Student');
        $this->get(route('items.show', $item))->assertOk();
        $this->get(route('items.create'))->assertOk()->assertSee('Shared teaching course');
    }

    #[TestWith(['student'])]
    #[TestWith(['teacher'])]
    public function test_only_admin_can_create_and_edit_courses(string $role): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->get(route('courses.create'))->assertForbidden();
        $this->post(route('courses.store'), $this->courseData())->assertForbidden();
        $this->put(route('courses.update', $course), $this->courseData())->assertForbidden();

        $this->assertDatabaseCount('courses', 1);
    }

    public function test_invalid_roles_duplicate_assignments_and_status_are_rejected(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('courses.store'), array_replace($this->courseData(), ['status' => 'archived', 'teachers' => [$student->id], 'students' => [$teacher->id]]))->assertSessionHasErrors(['status', 'teachers.0', 'students.0']);
        $this->post(route('courses.store'), $this->courseData() + ['teachers' => [$teacher->id, $teacher->id], 'students' => [$student->id, $student->id]])->assertSessionHasErrors(['teachers.0', 'students.0']);

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_inactive_course_rejects_new_uploads(): void
    {
        $course = Course::factory()->create(['status' => 'inactive']);
        $student = User::factory()->create();
        $course->students()->attach($student);

        $this->actingAs($student)->post(route('items.store'), ['course_id' => $course->id, 'title' => 'Report', 'description' => 'Description', 'type' => 'report', 'visibility' => 'course'])->assertSessionHasErrors('course_id');

        $this->assertDatabaseCount('academic_items', 0);
    }

    #[TestWith(['teacher'])]
    #[TestWith(['student'])]
    public function test_admin_can_save_assignments_from_course_management_modal(string $role): void
    {
        $course = Course::factory()->create();
        $member = User::factory()->create(['role' => $role]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('courses.index'))->assertOk()->assertSee('data-management-role', false)->assertSee('id="manage-'.$role.'"', false);
        $this->post(route('courses.assign'), ['course_id' => $course->id, 'role' => $role, 'members' => [$member->id]])->assertSessionHasNoErrors()->assertRedirect(route('courses.index'));

        $relationship = $role === 'teacher' ? 'teachers' : 'students';
        $this->assertTrue($course->{$relationship}->contains($member));
    }

    public function test_teacher_cannot_use_management_assignment_endpoint(): void
    {
        $course = Course::factory()->create();
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->post(route('courses.assign'), ['course_id' => $course->id, 'role' => 'teacher', 'members' => [$teacher->id]])->assertForbidden();

        $this->assertDatabaseCount('course_teacher', 0);
    }

    public function test_management_assignment_rejects_wrong_member_role(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->post(route('courses.assign'), ['course_id' => $course->id, 'role' => 'teacher', 'members' => [$student->id]])->assertSessionHasErrors('members.0');

        $this->assertDatabaseCount('course_teacher', 0);
    }

    public function test_editing_course_details_preserves_assignments_and_details_open_assignment_modal(): void
    {
        $course = Course::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $course->teachers()->attach($teacher);
        $course->students()->attach($student);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('courses.show', $course))->assertOk()->assertDontSee('<h2>Course repository</h2>', false)->assertSee('data-dialog-open="course-assignment-dialog"', false)->assertSee('data-course-assignment-role', false);
        $this->put(route('courses.update', $course), $this->courseData())->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue($course->teachers->contains($teacher));
        $this->assertTrue($course->students->contains($student));
        $this->post(route('courses.assign'), ['course_id' => $course->id, 'role' => 'student', 'members' => [], 'return_to_course' => 1])->assertRedirect(route('courses.show', $course));
        $this->assertCount(0, $course->fresh()->students);
    }

    #[TestWith(['project'])]
    #[TestWith(['report'])]
    #[TestWith(['notes'])]
    #[TestWith(['exam'])]
    #[TestWith(['assignment'])]
    #[TestWith(['notice'])]
    #[TestWith(['material'])]
    public function test_course_deletion_removes_assignments_resources_and_files_but_preserves_users(string $type): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('repository/course-file.txt', 'Content');
        $course = Course::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $course->teachers()->attach($teacher);
        $course->students()->attach($student);
        $item = AcademicItem::factory()->for($course)->for($student)->create(['type' => $type, 'file_path' => 'repository/course-file.txt']);
        $unrelated = AcademicItem::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('courses.index'))->assertOk()->assertSee('title="Delete course"', false);
        $this->get(route('courses.show', $course))->assertOk()->assertDontSee('data-confirm="Delete this course', false);
        $this->delete(route('courses.destroy', $course))->assertRedirect(route('courses.index'));

        $this->assertModelMissing($course);
        $this->assertModelMissing($item);
        $this->assertModelExists($teacher);
        $this->assertModelExists($student);
        $this->assertModelExists($unrelated);
        $this->assertDatabaseMissing('course_teacher', ['course_id' => $course->id]);
        $this->assertDatabaseMissing('course_user', ['course_id' => $course->id]);
        Storage::disk('local')->assertMissing('repository/course-file.txt');
    }

    public function test_teacher_cannot_delete_course(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create();
        $course->teachers()->attach($teacher);

        $this->actingAs($teacher)->delete(route('courses.destroy', $course))->assertForbidden();

        $this->assertModelExists($course);
    }

    public function test_course_management_renders_all_courses_in_datatable_with_edit_delete_actions(): void
    {
        $course = Course::factory()->create(['title' => 'Course table example']);
        Course::factory()->count(13)->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('courses.index'))->assertOk()->assertViewHas('courses', fn ($courses): bool => $courses->count() === 14)->assertSee('id="courses-table"', false)->assertSee('data-datatable', false)->assertSee(route('courses.show', $course))->assertSee(route('courses.edit', $course))->assertSee('button button-primary btn-primary', false)->assertSee('button button-danger btn-danger', false)->assertDontSee('class="course-grid"', false);
    }

    public function test_assigned_teacher_can_assign_students_but_not_teachers(): void
    {
        $course = Course::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $course->teachers()->attach($teacher);
        $this->actingAs($teacher);

        $this->get(route('courses.index'))->assertOk()->assertSee('id="manage-student"', false)->assertDontSee('id="manage-teacher"', false);
        $this->get(route('courses.show', $course))->assertOk()->assertSee('Assign students')->assertSee('name="role" value="student"', false)->assertDontSee('value="teacher"', false)->assertSee($student->email);
        $this->post(route('courses.assign'), ['course_id' => $course->id, 'role' => 'student', 'members' => [$student->id]])->assertSessionHasNoErrors()->assertRedirect(route('courses.index'));
        $this->post(route('courses.assign'), ['course_id' => $course->id, 'role' => 'teacher', 'members' => [$otherTeacher->id]])->assertForbidden();

        $this->assertTrue($course->students->contains($student));
        $this->assertSame([$teacher->id], $course->teachers()->pluck('users.id')->all());
    }

    public function test_teacher_cannot_assign_students_to_unrelated_courses(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create();
        $this->actingAs(User::factory()->teacher()->create());

        $this->post(route('courses.assign'), ['course_id' => $course->id, 'role' => 'student', 'members' => [$student->id]])->assertForbidden();
        $this->put(route('courses.enroll', $course), ['students' => [$student->id]])->assertForbidden();

        $this->assertDatabaseCount('course_user', 0);
    }

    public function test_teacher_can_add_students_but_cannot_unassign_existing_students_using_either_endpoint(): void
    {
        $course = Course::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $existing = User::factory()->create();
        $added = User::factory()->create();
        $course->teachers()->attach($teacher);
        $course->students()->attach($existing);
        $this->actingAs($teacher);

        $this->get(route('courses.show', $course))->assertOk()->assertSee('Only administrators can unassign students.');
        $this->post(route('courses.assign'), ['course_id' => $course->id, 'role' => 'student', 'members' => [$added->id]])->assertSessionHasNoErrors();
        $this->post(route('courses.assign'), ['course_id' => $course->id, 'role' => 'student', 'members' => []])->assertSessionHasNoErrors();
        $this->put(route('courses.enroll', $course), ['students' => []])->assertSessionHasNoErrors();

        $this->assertCount(2, $course->fresh()->students);
        $this->assertTrue($course->fresh()->students->contains($existing));
        $this->assertTrue($course->fresh()->students->contains($added));

        $this->actingAs(User::factory()->admin()->create())->post(route('courses.assign'), ['course_id' => $course->id, 'role' => 'student', 'members' => []])->assertSessionHasNoErrors();
        $this->assertCount(0, $course->fresh()->students);
    }

    /** @return array<string, string> */
    private function courseData(): array
    {
        return ['title' => 'Computer Science', 'semester' => 'Autumn 2026', 'status' => 'active'];
    }
}
