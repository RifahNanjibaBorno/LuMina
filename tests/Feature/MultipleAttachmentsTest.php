<?php

namespace Tests\Feature;

use App\Models\AcademicItem;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultipleAttachmentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_multiple_files_are_saved_listed_downloaded_and_appended_on_edit(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $data = ['course_id' => $course->id, 'title' => 'Materials', 'description' => 'Course files', 'type' => 'material', 'visibility' => 'course'];

        $this->post(route('items.store'), $data + ['files' => [UploadedFile::fake()->createWithContent('first.txt', 'One'), UploadedFile::fake()->createWithContent('second.txt', 'Two')]])->assertSessionHasNoErrors()->assertRedirect();

        $item = AcademicItem::where('title', 'Materials')->firstOrFail();
        $this->assertCount(2, $item->uploadedFiles());
        $this->assertSame(6, (int) $item->file_size);
        $this->get(route('items.edit', $item))->assertOk()->assertSee('first.txt')->assertSee('second.txt');
        $this->get(route('items.show', $item))->assertOk()->assertSee('Download first.txt')->assertSee('Download second.txt');
        $this->get(route('items.download', ['item' => $item, 'attachment' => 1]))->assertDownload('second.txt');
        $this->put(route('items.update', $item), $data + ['files' => [UploadedFile::fake()->createWithContent('third.txt', 'Three')]])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertCount(3, $item->fresh()->uploadedFiles());
        foreach ($item->fresh()->uploadedFiles() as $file) {
            Storage::disk('local')->assertExists($file['path']);
        }
        $this->delete(route('items.destroy', $item))->assertRedirect();
        foreach ($item->uploadedFiles() as $file) {
            Storage::disk('local')->assertMissing($file['path']);
        }
    }

    public function test_invalid_file_in_multiple_upload_is_rejected_without_saving_resource(): void
    {
        Storage::fake('local');
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('items.store'), ['course_id' => $course->id, 'title' => 'Materials', 'description' => 'Files', 'type' => 'material', 'visibility' => 'course', 'files' => [UploadedFile::fake()->createWithContent('valid.txt', 'Text'), UploadedFile::fake()->createWithContent('bad.php', '<?php echo 1;')]])->assertSessionHasErrors('files.1');

        $this->assertDatabaseCount('academic_items', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_each_attachment_download_enforces_resource_permissions(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('repository/private.txt', 'Private');
        $item = AcademicItem::factory()->create(['visibility' => 'private', 'attachments' => [['path' => 'repository/private.txt', 'name' => 'private.txt', 'size' => 7]]]);

        $this->actingAs(User::factory()->create())->get(route('items.download', ['item' => $item, 'attachment' => 0]))->assertForbidden();
        $this->actingAs($item->user)->get(route('items.download', ['item' => $item, 'attachment' => 99]))->assertNotFound();
    }

    public function test_course_deletion_removes_every_attachment(): void
    {
        Storage::fake('local');
        $course = Course::factory()->create();
        $attachments = [];
        foreach (['first.txt', 'second.txt'] as $name) {
            Storage::disk('local')->put('repository/'.$name, 'Content');
            $attachments[] = ['path' => 'repository/'.$name, 'name' => $name, 'size' => 7];
        }
        AcademicItem::factory()->for($course)->create(['attachments' => $attachments]);

        $this->actingAs(User::factory()->admin()->create())->delete(route('courses.destroy', $course))->assertRedirect();

        Storage::disk('local')->assertMissing(['repository/first.txt', 'repository/second.txt']);
    }
}
