<?php

namespace Tests\Feature;

use App\Models\AcademicItem;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ResourceTypesTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['notice'])]
    #[TestWith(['material'])]
    public function test_new_resource_types_can_be_created_edited_and_filtered(string $type): void
    {
        $this->withoutVite();
        $course = Course::factory()->create(['title' => 'Computer Science']);
        $this->actingAs(User::factory()->admin()->create());
        $data = ['title' => 'Course update', 'description' => 'Course content', 'course_id' => $course->id, 'type' => $type, 'visibility' => 'course', 'team_members' => 3];

        $this->get(route('items.create'))->assertOk()->assertSee('Notice')->assertSee('Material')->assertSee('>Computer Science</option>', false)->assertDontSee('>'.$course->id.' ', false);
        $this->post(route('items.store'), $data)->assertSessionHasNoErrors()->assertRedirect();

        $item = AcademicItem::where('title', 'Course update')->firstOrFail();
        $this->assertSame($type, $item->type);
        $this->assertDatabaseHas('academic_items', ['id' => $item->id, 'team_members' => 3]);
        $this->get(route('repository.index', ['type' => $type]))->assertOk()->assertSee('Course update');
        $this->put(route('items.update', $item), array_replace($data, ['title' => 'Updated content']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Updated content', $item->fresh()->title);
    }

    #[TestWith(['Alice, Bob'])]
    #[TestWith(['2.5'])]
    #[TestWith([0])]
    #[TestWith([-1])]
    public function test_team_members_must_be_a_positive_whole_number(string|int $count): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('items.store'), ['title' => 'Project', 'description' => 'Description', 'course_id' => $course->id, 'type' => 'project', 'visibility' => 'course', 'team_members' => $count])->assertSessionHasErrors('team_members');

        $this->assertDatabaseCount('academic_items', 0);
    }

    public function test_edit_upload_shows_saved_filename_and_keeps_file_when_no_replacement_is_selected(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        Storage::disk('local')->put('repository/report.pdf', 'Saved content');
        $item = AcademicItem::factory()->create(['file_path' => 'repository/report.pdf', 'file_name' => 'Course Report.pdf']);
        $item->course->students()->attach($item->user);
        $this->actingAs($item->user);

        $this->get(route('items.edit', $item))->assertOk()->assertSee('Uploaded file')->assertSee('Course Report.pdf')->assertSee('Add more files (optional)');
        $this->put(route('items.update', $item), ['title' => 'Updated report', 'description' => 'Description', 'course_id' => $item->course_id, 'type' => 'report', 'visibility' => 'course'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Course Report.pdf', $item->fresh()->file_name);
        Storage::disk('local')->assertExists('repository/report.pdf');
    }

    public function test_repository_uses_datatable_with_all_visible_resources_and_excludes_private_resources(): void
    {
        $this->withoutVite();
        AcademicItem::factory()->count(12)->create(['visibility' => 'public']);
        AcademicItem::factory()->create(['visibility' => 'private', 'title' => 'Hidden private item']);

        $this->get(route('repository.index'))->assertOk()->assertViewHas('items', fn ($items): bool => $items->count() === 12)->assertSee('id="repository-table"', false)->assertSee('data-datatable', false)->assertDontSee('Hidden private item')->assertDontSee('class="repository-results"', false)->assertDontSee('class="search-bar"', false);
    }
}
