<?php

namespace Database\Seeders;

use App\Models\AcademicItem;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo accounts are available only in local and testing environments.');
        }
        $people = [
            ['Rifah Nanjiba Borno', 'rifah@lumina.test', '0432320005101083', 'student', 'rifah-borno'],
            ['Tasnim Rahman', 'tasnim@lumina.test', '0432320005101092', 'student', 'tasnim-rahman'],
            ['Fayruz Humayra', 'fayruz@lumina.test', '0432320005101096', 'student', 'fayruz-humayra'],
            ['Dr. Mahfida Amjad Dipa', 'teacher@gmail.com', null, 'teacher', 'mahfida-dipa'],
            ['Administrator', 'admin@gmail.com', null, 'admin', 'administrator'],
        ];
        $members = collect();
        foreach ($people as [$name, $email, $universityId, $role, $slug]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'university_id' => $universityId, 'password' => 'LuminaDemo2026!', 'portfolio_slug' => $slug, 'portfolio_public' => $role === 'student', 'visible_sections' => ['bio', 'education', 'skills', 'projects', 'links'], 'profile' => [
                'department' => 'Computer Science & Engineering', 'semester' => 'Autumn 2026', 'batch' => '54 (7B)',
                'bio' => 'CSE student building useful software and exploring the intersection of education and technology.',
                'education' => "University of Information Technology & Sciences\nBSc in Computer Science & Engineering\nExpected graduation: 2027",
                'skills' => 'Laravel, Python, MySQL, Bootstrap, JavaScript, Git',
                'experience' => 'Academic team projects in web development and database systems.',
            ]]);
            if ($user->wasRecentlyCreated) {
                $user->forceFill(['role' => $role])->save();
            }
            $members->push($user);
        }
        $teacher = $members[3];
        $courses = collect();
        foreach ([['CSE416', 'Software Project Design & Development'], ['CSE410', 'Machine Learning'], ['CSE403', 'Data Communication'], ['CSE401', 'Database Systems'], ['CSE312', 'Web Application Development']] as [$code, $title]) {
            $course = Course::firstOrCreate(['code' => $code], ['title' => $title, 'semester' => 'Autumn 2026', 'teacher_id' => $teacher->id, 'description' => 'Course projects and academic resources for '.$title.'.']);
            $course->students()->syncWithoutDetaching($members->take(3)->pluck('id')->all());
            $courses->put($code, $course);
        }
        $entries = [
            [0, 'CSE416', 'Automated Student Digital Portfolio Builder', 'project', 'Laravel, Python, NLP', 'Education', 'A centralized platform that transforms student resumes into shareable digital portfolios and organizes academic resources.'],
            [0, 'CSE312', 'Smart Attendance System', 'project', 'Laravel, MySQL', 'Education', 'Course-based attendance management with student records and reporting.'],
            [0, 'CSE410', 'Phishing Detection Model', 'project', 'Python, Machine Learning', 'Security', 'An academic experiment in detecting suspicious URLs using machine learning.'],
            [1, 'CSE416', 'NLP Resume Parser', 'project', 'Python, NLP', 'Education', 'Resume text extraction and structured section mapping for student profiles.'],
            [2, 'CSE416', 'Academic Resource Search', 'project', 'Laravel, MySQL', 'Education', 'A course-wise search interface for projects, reports, notes and examination resources.'],
            [0, 'CSE312', 'Campus Event Management System', 'project', 'Laravel, Bootstrap', 'Campus', 'An academic project for organizing campus events and registrations.'],
            [3, 'CSE401', 'Database Normalization Case Study', 'report', null, null, 'A practice guide to functional dependencies, first normal form, second normal form and third normal form.'],
            [3, 'CSE410', 'Machine Learning Final Revision', 'notes', null, null, 'Revision notes on supervised learning, classification, regression and model evaluation.'],
            [3, 'CSE403', 'Data Communication Study Guide', 'notes', null, null, 'A study resource covering network models, transmission media and error detection.'],
        ];
        foreach ($entries as [$owner, $code, $title, $type, $technology, $domain, $description]) {
            $item = AcademicItem::firstOrCreate(['user_id' => $members[$owner]->id, 'title' => $title], ['course_id' => $courses[$code]->id, 'description' => $description, 'type' => $type, 'technology' => $technology, 'domain' => $domain, 'visibility' => 'public', 'is_featured' => $owner === 0 && $code !== 'CSE410', 'team_members' => $type === 'project' ? 'Rifah, Tasnim, Fayruz' : null]);
            if ($item->wasRecentlyCreated) {
                if ($type !== 'project') {
                    $content = $title."\n\n".$description."\n\nDemo academic resource for LUMiNA. Replace with your course materials.\n";
                    $path = 'repository/demo-'.$item->id.'.txt';
                    Storage::disk('local')->put($path, $content);
                    $item->update(['file_path' => $path, 'file_name' => str_replace(' ', '_', $title).'.txt', 'file_size' => strlen($content)]);
                }
                Activity::create(['user_id' => $members[$owner]->id, 'title' => $title, 'action' => 'Upload created', 'course_code' => $code]);
            }
        }
    }
}
