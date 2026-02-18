<?php

namespace App\Http\Controllers;

use App\Helpers\Auth\Auth;
use App\Mail\Frontend\LiveLesson\StudentMeetingSlotMail;
use App\Models\{Assignment, Lesson, AttendanceStudent, Course, CourseFeedback, EmployeeCourseProgress, UserFeedback};
use App\Models\Stripe\SubscribeCourse;
use App\Models\Auth\User;
use App\Models\LessonSlotBooking;
use App\Models\LiveLessonSlot;
use App\Models\Media;
use App\Models\{Question, TestQuestion};
use App\Models\QuestionsOption;
use App\Models\Test;
use App\Models\TestsResult;
use App\Models\VideoProgress;
use Illuminate\Http\Request;
use App\Helpers\CustomHelper;
use App\Models\EmployeeProfile;
use App\Models\courseAssignment;
use Yajra\DataTables\DataTables;
use DB;
use Carbon\Carbon;


class LessonsController extends Controller
{

    private $path;

    public function __construct()
    {
        $path = 'frontend';
        if (session()->has('display_type')) {
            if (session('display_type') == 'rtl') {
                $path = 'frontend-rtl';
            } else {
                $path = 'frontend';
            }
        } else if (config('app.display_type') == 'rtl') {
            $path = 'frontend-rtl';
        }
        $this->path = $path;
    }

    public function isAssignmentTaken($logged_in_user_id, $course_id)
    {



        $employee_profile = EmployeeProfile::where('user_id', $logged_in_user_id)->first();
        $logged_in_department_id = $employee_profile ? $employee_profile->department : null;
        if (!empty($employee_profile) && !empty($logged_in_department_id)) {
            $assignments = courseAssignment::with(['assessment', 'assessment.course'])
                ->whereRaw('FIND_IN_SET(?, `assign_to`) > 0', $logged_in_user_id)
                ->where('course_assignment.course_id', $course_id)
                ->whereNotNull('course_id')
                ->get();
        } else {
            $assignments = courseAssignment::with(['assessment', 'assessment.course'])
                ->where('assign_to', $logged_in_user_id)
                ->where('course_assignment.course_id', $course_id)
                ->get();
        }

        $assignment_taken = false;
        foreach ($assignments as $q) {
            $assignment_taken = false;
            $test_taken = CustomHelper::is_test_taken(@$q->assessment->id, $logged_in_user_id);
            if ($test_taken) {
                $assignment_taken = true;
            }
        }

        return $assignment_taken;
    }

    public function hasAssessmentLink($course_id, $logged_in_user_id)
    {

        $agmt = Assignment::where('assignments.course_id', $course_id)
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->join('course_assignment', 'course_assignment.course_id', '=', 'courses.id')
            ->join('tests', 'tests.id', '=', 'assignments.test_id')
            ->join('test_questions', 'test_questions.test_id', '=', 'tests.id')
            ->whereRaw('FIND_IN_SET(?, `assign_to`) > 0', $logged_in_user_id)
            ->exists();
        if ($agmt) {
            return true;
        }

        return false;
    }

    public function courseFeedbackLink($course_id)
    {
        $cf = CourseFeedback::firstWhere('course_id', $course_id);
        if ($cf) {
            $uf = UserFeedback::where("user_id", auth()->id())->where('course_id', $course_id)->first();
            if (!$uf) {
                return route('course-feedback', ['course_id' =>  $course_id]);
            }
        }

        return route('course-feedback', ['course_id' =>  $course_id]);
    }

    public function courseHasFeedbackLink($course_id)
    {
        $cf = CourseFeedback::firstWhere('course_id', $course_id);
        if ($cf) {
            return true;
        }

        return false;
    }

    public function assessmentLink($logged_in_user_id, $course_id)
    {
       
        $employee_profile = EmployeeProfile::where('user_id', $logged_in_user_id)->first();
        $logged_in_department_id = $employee_profile ? $employee_profile->department : null;

        

        if (!empty($employee_profile) && !empty($logged_in_department_id)) {
           
            $assignment = CourseAssignment::with(['assessment', 'assessment.course'])
                ->whereRaw('FIND_IN_SET(?, assign_to) > 0', $logged_in_user_id)
                ->where('course_assignment.course_id', $course_id)
                ->whereNotNull('course_id')
                ->latest('course_assignment.id')
                ->first();
            //dd($assignment);
        }  
        
        

        if (!isset($assignment)) {
            $assignment = CourseAssignment::with(['assessment', 'assessment.course'])
                ->where('assign_to', $logged_in_user_id)
                ->where('course_assignment.course_id', $course_id)
                ->latest('course_assignment.id')
                ->first();
        }
        
        //dd($assignment);

        // If no assignment found, fallback
        if (!isset($assignment)) {

            CourseAssignment::create(
                [
                    'course_id' => $course_id,
                    'assign_by' => $logged_in_user_id,
                    'assign_to' => $logged_in_user_id
                ]
            );


            $assignment = CourseAssignment::with(['assessment', 'assessment.course'])
                ->where('course_assignment.course_id', $course_id)
                ->where('assign_to', $logged_in_user_id)
                ->latest('course_assignment.id')
                ->first();

            //dd( $assignment );
            
        }

        //dd( $assignment );

        // If still nothing
        if (!$assignment) {
            return '';
        }

        // Build the assessment URL
        if ($assignment->assessment) {

            $test_taken = CustomHelper::assignmentAttempts($assignment->assessment->id, $logged_in_user_id);

            //dd($test_taken);

            $allowedAssignmentRetake = 2;
            //dd($allowedAssignmentRetake, $test_taken);

            if ($test_taken < $allowedAssignmentRetake) {
                return route('online_assessment', [
                    'assignment'     => $assignment->assessment->url_code,
                    'verify_code'    => $assignment->assessment->verify_code,
                    'id'             => $assignment->id,
                    'assessment_id'  => $assignment->assessment->id,
                    'course_id'      => $course_id
                ]);
            }
        }

        return '';
    }


    public function assessmentLink_($logged_in_user_id, $course_id)
    {



        $employee_profile = EmployeeProfile::where('user_id', $logged_in_user_id)->first();
        $logged_in_department_id = $employee_profile ? $employee_profile->department : null;
        if (!empty($employee_profile) && !empty($logged_in_department_id)) {
            $assignments = courseAssignment::with(['assessment', 'assessment.course'])
                ->whereRaw('FIND_IN_SET(?, `assign_to`) > 0', $logged_in_user_id)
                ->where('course_assignment.course_id', $course_id)
                ->whereNotNull('course_id')
                ->get();
        } else {
            $assignments = courseAssignment::with(['assessment', 'assessment.course'])
                ->where('assign_to', $logged_in_user_id)
                ->where('course_assignment.course_id', $course_id)
                ->get();
        }

        if ($assignments->count() == 0) {
            $assignments = courseAssignment::with(['assessment', 'assessment.course'])
                //->where('assign_to', $logged_in_user_id)
                ->where('course_assignment.course_id', $course_id)
                ->orderBy('course_assignment.id', 'desc')
                ->get();
            //dd( $assignments );
        }




        // $test_taken = CustomHelper::is_test_taken($assignments->assessment->id,$logged_in_user_id);

        $dd = DataTables::of($assignments)->addColumn('assesment_url', function ($q) use ($logged_in_user_id, $course_id) {
            if ($q->assessment) {
                $test_taken = CustomHelper::assignmentAttempts($q->assessment->id, $logged_in_user_id);
                //echo '<pre>';   print_r([$test_taken]);die;

                $allowedAssignmentRetake = 2;

                if ($test_taken < $allowedAssignmentRetake) {
                    $test_link = route('online_assessment', ['assignment' => $q->assessment->url_code, 'verify_code' => $q->assessment->verify_code, 'id' => $q->id, 'assessment_id' => $q->assessment->id, 'course_id' => $course_id]);
                    return  $test_link;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        })->make();

        return isset($dd->original['data'][0]['assesment_url']) ? $dd->original['data'][0]['assesment_url'] : '';
    }

    public function show($course_id, $lesson_slug = null)
    {
        //dd("hi");
        $test_result = "";
        $completed_lessons = "";

        $test_pass = "";
        $total_questions = "";
        $percentage = "";
        $assessment_link = "";

        $logged_in_user_id = auth()->user()->id;

        $sc = SubscribeCourse::where('course_id', $course_id)
            ->where('user_id', $logged_in_user_id)
            ->first();

        $isAssignmentTaken = $sc->assesment_taken ?? $this->isAssignmentTaken($logged_in_user_id, $course_id);
        $hasAssessmentLink = $sc->has_assesment ?? $this->hasAssessmentLink($course_id, $logged_in_user_id);

        //dd($hasAssessmentLink);

        $completed_at = $sc->is_completed ? $sc->completed_at : null;

        $is_certificate_download = false;
        $is_assesment_taken = false;
        $is_feedback_taken = false;
        $has_feedback = false;
        $has_assesment = false;

        $is_attended = $sc->is_attended ?? 0;

        //dd($logged_in_user_id, $isAssignmentTaken, $hasAssessmentLink);

        $courseFeedbackLink = '';
        if ($hasAssessmentLink) {
            $assessment_link = $this->assessmentLink($logged_in_user_id, $course_id);
            //dd($assessment_link);
        }
        //dd($this->assessmentLink($logged_in_user_id, $course_id), $hasAssessmentLink,  "hhh");

        //dd($isAssignmentTaken, $hasAssessmentLink, $assessment_link);

        if (!$hasAssessmentLink && CustomHelper::courseProgress($course_id, $logged_in_user_id) == 100) {
            $courseFeedbackLink = $this->courseFeedbackLink($course_id);
        }


        $assignment_status = @$sc->course->assignmentStatus($logged_in_user_id);

        //dd($sc);

        if ($sc) {
            $is_certificate_download = $sc->course_progress_status == 2 ? true : false;
            $is_assesment_taken = $sc->assesment_taken ?? false;
            $is_feedback_taken = $sc->feedback_given ?? false;;
            $has_feedback = $sc->has_feedback ?? false;
            $has_assesment = $sc->has_assesment ?? false;;
        }




        $lesson = Lesson::with(['downloadableMedia', 'mediaVideo', 'mediaPDF'])->where('slug', $lesson_slug)->where('course_id', $course_id)->where('published', '=', 1)->first();
        //dd($lesson, $lesson_slug, $course_id); 

        if ($lesson == "") {
            $lesson = Test::where('slug', $lesson_slug)->where('course_id', $course_id)->where('published', '=', 1)->firstOrFail();
            $lesson->full_text = $lesson->description;
            $test_result = TestsResult::where('test_id', $lesson->id)
                ->where('user_id', \Auth::id())
                ->first();
            //dd($test_result);
            if ($lesson && $test_result) {

                // get the tes't score

                $total_questions = $lesson->questions->count();
                $percentage = $test_result->test_result / $total_questions * 100;
                $test_pass = ($percentage < $lesson->passing_score) ? "Failed" : "Pass";
            }
        }


        //dd($lesson, $lesson->mediaVideo()->exists());
        if (!$lesson->mediaVideo()->exists()) {

            $custom_helper = new CustomHelper();
            $custom_helper->updateUserProgress($logged_in_user_id, $sc->course->id);
        }



        if ((int)config('lesson_timer') == 0) {
            if (!$lesson->live_lesson && !$lesson->mediaVideo) {
                if ($lesson->chapterStudents()->where('user_id', \Auth::id())->count() == 0) {
                    $lesson->chapterStudents()->create([
                        'model_type' => get_class($lesson),
                        'model_id' => $lesson->id,
                        'user_id' => auth()->user()->id,
                        'course_id' => $lesson->course->id
                    ]);

                    // Save the attendance
                    $data = [
                        'student_id' => \Auth::id(),
                        'course_id' => $lesson->course->id,
                        'lesson_id' => $lesson->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    AttendanceStudent::create($data);
                }
            }
        }

        //dd("hh");

        $course_lessons = $lesson->course->lessons->pluck('id')->toArray();
        $course_lessons_arr = $lesson->course->lessons;
        $lessonCount = count($course_lessons);
        $course_tests = ($lesson->course->tests) ? $lesson->course->tests->pluck('id')->toArray() : [];
        $timeline = $lesson->courseTimeline()->customScope($course_id)->first();
        $sequence = $timeline->sequence ?? 0;
        $previous_lesson = $lesson->course->courseTimeline()
            ->where('sequence', '<', $sequence)
            ->whereIn('model_id', $course_lessons)
            ->orderBy('sequence', 'desc')
            ->first();

        $next_lesson = $lesson->course->courseTimeline()
            ->whereIn('model_id', $course_lessons)
            ->where('sequence', '>', $sequence)
            ->orderBy('sequence', 'asc')
            ->first();


        $lessons = $lesson->course->courseTimeline()
            ->whereIn('model_id', $course_lessons)
            ->orderby('id', 'asc')
            ->get();


        $purchased_course = $lesson->course->students()->where('user_id', \Auth::id())->count() > 0;
        $test_exists = FALSE;

        //dd("hh");

        if (!empty($course_lessons)) {

            $lesson->course->courseTimeline()->whereIn('model_id', $course_lessons)->orderby('id', 'asc')->get();
            //dd($l);
            //dd($lesson->course->courseTimeline()->orderBy('id')->get());
        } else {
        }

        //dd($lesson->course->courseTimeline()->orderBy('id')->get());

        //dd($lesson->course->progress());
        //dd($lesson->course->lessonProgress());
        if (get_class($lesson) == 'App\Models\Test') {
            $test_exists = TRUE;
        }
        //dd($lesson->media);
        $lesson_media = $lesson->media;

        $mediavideo = (isset($lesson_media[0])) ? $lesson_media[0] : '';

        $completed_lessons = \Auth::user()->chapters()
            ->where('course_id', $lesson->course->id)
            ->get()
            ->pluck('model_id')
            ->toArray();

        //dd("hhd");

        $is_course_completed = SubscribeCourse::where('course_id', $course_id)
            ->where('user_id', auth()->user()->id)
            ->where('is_completed', 1)
            ->count() ?? 0;

        $course_detail = Course::query()
            ->where('id',  $course_id)
            ->first();

        $is_offline_course = false;

        if ($course_detail->is_online == 'Offline') {
            $is_offline_course = true;
        }

        //dd($is_offline_course);
        //dd("99");



        if ($is_course_completed) {
            //dd("jj");
            $course_lessons  = Lesson::where('course_id', $course_id)
                ->when(!empty($completed_at), function ($q) use ($completed_at) {
                    return $q->where('created_at', '<', $completed_at);
                })
                ->where('published', 1)
                ->get();
            $lessonCount = count($completed_lessons);
        } else {
            //dd("jj2");
            $course_lessons  = Lesson::where('course_id', $course_id)
                ->where('published', 1)
                ->get();
            $lessonCount = $course_lessons->count();
        }

        //dd("kk");

        $lessons = $course_lessons;

        $course_lessons_arr = $course_lessons;

        //dd($has_assesment, $is_assesment_taken, $has_feedback, $is_feedback_taken,  $is_offline_course, $is_certificate_download, $is_course_completed);

        $is_certificate_download = $sc->grant_certificate ?? 0;

        //dd($lessonCount, );
        $lessonCompletedCount = count($completed_lessons);
        //dd($lessonCompletedCount, $course_lessons);

        $nextTasks = CustomHelper::getNextTask($sc, $course_id);
        //dd($nextTasks, $assessment_link);

        return view($this->path . '.courses.lesson', compact(
            'is_certificate_download',
            'is_assesment_taken',
            'is_feedback_taken',
            'has_feedback',
            'has_assesment',
            'nextTasks',
            'lessonCompletedCount',
            'is_attended',
            'is_offline_course',
            'is_course_completed',
            'mediavideo',
            'assessment_link',
            'course_lessons',
            'lessonCount',
            'lesson',
            'previous_lesson',
            'next_lesson',
            'test_result',
            'purchased_course',
            'test_exists',
            'lessons',
            'completed_lessons',
            'test_pass',
            'percentage',
            'total_questions',
            'course_id',
            'isAssignmentTaken',
            'courseFeedbackLink',
            'assignment_status',
            'course_lessons_arr'
        ));
    }

    public function test($lesson_slug, Request $request)
    {
        //dd($request->all());
        $test = Test::where('slug', $lesson_slug)->firstOrFail();
        //dd($request->get('questions'));
        $answers = [];
        $test_score = 0;
        $total_score = 0;
        if (!$request->get('questions')) {

            return back()->with(['flash_warning' => 'No options selected']);
        }
        foreach ($request->get('questions') as $question_id => $answer_id) {
            $question = Question::find($question_id);
            $correct = QuestionsOption::where('question_id', $question_id)
                ->where('id', $answer_id)
                ->where('correct', 1)->count() > 0;
            $answers[] = [
                'question_id' => $question_id,
                'option_id' => $answer_id,
                'correct' => $correct
            ];
            if ($correct) {
                if ($question->score) {
                    $test_score += $question->score;
                }
            }
            /*
             * Save the answer
             * Check if it is correct and then add points
             * Save all test result and show the points
             */
        }
        $test_result = TestsResult::create([
            'test_id' => $test->id,
            'user_id' => \Auth::id(),
            'test_result' => $test_score,
            'test_score' => $total_score,
            'course_id' => $test->course_id,
        ]);
        $test_result->answers()->createMany($answers);

        // get the test score

        $total_questions = count($request->get('questions'));
        $percentage = $test_score / $total_questions * 100;
        $test_pass = ($percentage < $test->passing_score) ? "Failed" : "Pass";

        if ($test->chapterStudents()->where('user_id', \Auth::id())->get()->count() == 0) {
            $test->chapterStudents()->create([
                'model_type' => $test->model_type,
                'model_id' => $test->id,
                'user_id' => auth()->user()->id,
                'course_id' => $test->course->id
            ]);
        }

        return back()->with([
            'message' => 'Test score: ' . $test_score,
            'result' => $test_result,
            'test_percentage' => $percentage,
            'test_pass' => $test_pass,
            'total_score' => $total_score
        ]);
    }

    public function retest(Request $request)
    {
        $test = TestsResult::where('id', '=', $request->result_id)
            ->where('user_id', '=', auth()->user()->id)
            ->first();
        $test->delete();
        return back();
    }

    public function videoProgress(Request $request)
    {
        //dd( $request->all(), "rrrr" );
        $user = auth()->user();
        $video = Media::findOrFail($request->video);
        $video_progress = VideoProgress::where('user_id', '=', $user->id)
            ->where('media_id', '=', $video->id)->first() ?: new VideoProgress();
        $video_progress->media_id = $video->id;
        $video_progress->user_id = $user->id;
        $video_progress->duration = $video_progress->duration ?: round($request->duration, 2);
        $video_progress->progress = round($request->progress, 2);
        if ($video_progress->duration - $video_progress->progress < 5) {
            $video_progress->progress = $video_progress->duration;
            $video_progress->complete = 1;
        }
        $video_progress->save();

        /**
         * check if video has been watched upto certain percentage in order to mark lesson as completed
         */
        $percentageCompleted = $video->getProgressPercentage($user->id);
        $percentageToMarkLessonAsCompleted = 90;
        if ($percentageCompleted >= $percentageToMarkLessonAsCompleted) {
            // mark lesson as completed
            $lesson = Lesson::find($video->model_id);
            if ($lesson->chapterStudents()->where('user_id', \Auth::id())->count() == 0) {
                $lesson->chapterStudents()->create([
                    'model_type' => get_class($lesson),
                    'model_id' => $lesson->id,
                    'user_id' => auth()->user()->id,
                    'course_id' => $lesson->course->id
                ]);

                // Save the attendance
                $data = [
                    'student_id' => \Auth::id(),
                    'course_id' => $lesson->course->id,
                    'lesson_id' => $lesson->id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                AttendanceStudent::create($data);
            }
        }
        return $video_progress->progress;
    }

    public function videoProgressUpdates(Request $request)
    {





        $user = auth()->user();
        $video = Media::findOrFail($request->vedio_id);

        $lesson = Lesson::find($video->model_id);
        $course_id = $lesson->course->id ?? null;

        if ($course_id && $user) {
            $already_completed = CustomHelper::isCourseAlreadyCompleted($user->id, $course_id);
            if ($already_completed) {
                return;
            }
        }

        //dd($already_completed);

        if ($already_completed == 0) {
            $video_progress = VideoProgress::where('user_id', '=', $user->id)->where('media_id', '=', $video->id)->first() ?: new VideoProgress();

            $video_progress->media_id = $video->id;
            $video_progress->user_id = $user->id;
            $video_progress->progress_per = $request->watchPoint;
            if ($video_progress->progress_per == '100') {
                $video_progress->complete = 1;
            }

            $video_progress->duration = $video_progress->duration ?: round($request->duration, 2);
            $video_progress->progress = round($request->progress, 2);
            $video_progress->save();

            // progress update for user
            $video = Media::findOrFail($request->vedio_id);

            $course_id = null;
            /**
             * check if video has been watched upto certain percentage in order to mark lesson as completed
             */
            $percentageCompleted = $video->getProgressPercentage($user->id);
            //dd($percentageCompleted);
            $percentageToMarkLessonAsCompleted = 90;



            //if ($percentageCompleted >= $percentageToMarkLessonAsCompleted) {
            if ($percentageCompleted >= $percentageToMarkLessonAsCompleted) {


                // mark lesson as completed
                $lesson = Lesson::find($video->model_id);

                $course_id = $lesson->course->id ?? null;

                //dd($course_id);

                //dd($lesson->chapterStudents()->get());
                if ($lesson->chapterStudents()->where('user_id', \Auth::id())->count() == 0) {
                    $lesson->chapterStudents()->create([
                        'model_type' => get_class($lesson),
                        'model_id' => $lesson->id,
                        'user_id' => auth()->user()->id,
                        'course_id' => $lesson->course->id
                    ]);

                    // Save the attendance
                    $data = [
                        'student_id' => \Auth::id(),
                        'course_id' => $lesson->course->id,
                        'lesson_id' => $lesson->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    AttendanceStudent::create($data);

                    //dd($lesson->course->id);

                    if ($lesson->course->id) {

                        //dd($lesson->course->id, \Auth::id());
                        $progressdata = CustomHelper::updateUserProgress(\Auth::id(), $lesson->course->id);
                    }
                }
            }


            $lesson = Lesson::find($video->model_id);
            $course_id = $lesson->course->id ?? null;

            if ($course_id) {

                //CustomHelper::updateUserProgress(\Auth::id(), $course_id);

                EmployeeCourseProgress::updateOrCreate(
                    [
                        'user_id' => \Auth::id(),
                        'course_id' => $course_id
                    ],
                    [
                        'is_cron_run' => 0,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                    ]
                );

                $sub_data = SubscribeCourse::where('course_id', $course_id)
                    ->where('user_id', \Auth::id())
                    ->first();

                if ($sub_data && $sub_data->course_progress_status == 0 && $percentageCompleted >= $percentageToMarkLessonAsCompleted) {
                    $sub_data->update([
                        'course_progress_status' => 1
                    ]);
                }


                CustomHelper::updateGrantCertificate($course_id, \Auth::id());

            }


            return response()->json([
                'progress_per' => $video_progress->progress_per,
                'lesson_completed' => $percentageCompleted >= $percentageToMarkLessonAsCompleted,
            ]);
        }
    }


    public function courseProgress(Request $request)
    {
        //dd($request->all());
        if (\Auth::check()) {
            $lesson = Lesson::find($request->model_id);
            if ($lesson != null) {
                if ($lesson->chapterStudents()->where('user_id', \Auth::id())->get()->count() == 0) {
                    $lesson->chapterStudents()->create([
                        'model_type' => $request->model_type,
                        'model_id' => $request->model_id,
                        'user_id' => auth()->user()->id,
                        'course_id' => $lesson->course->id
                    ]);
                    return true;
                }
            }
        }
        return false;
    }

    public function bookSlot(Request $request)
    {
        $lesson_slot = LiveLessonSlot::find($request->live_lesson_slot_id);
        $lesson = $lesson_slot->lesson;

        if ((int)config('lesson_timer') == 0) {
            if ($lesson->chapterStudents()->where('user_id', \Auth::id())->count() == 0) {
                $lesson->chapterStudents()->create([
                    'model_type' => get_class($lesson),
                    'model_id' => $lesson->id,
                    'user_id' => auth()->user()->id,
                    'course_id' => $lesson->course->id
                ]);
            }
        }

        if (LessonSlotBooking::where('lesson_id', $request->lesson_id)->where('user_id', auth()->user()->id)->count() == 0) {
            LessonSlotBooking::create(
                ['lesson_id' => $request->lesson_id, 'live_lesson_slot_id' => $request->live_lesson_slot_id, 'user_id' => auth()->user()->id]
            );
            \Mail::to(auth()->user()->email)->send(new StudentMeetingSlotMail($lesson_slot));
        }
        return back()->with(['success' => __('alerts.frontend.course.slot_booking')]);
    }

    public function attendance_lesson(Request $request, $course_id, $lesson_id)
    {
        $lessons_list = Lesson::with('course')->where('course_id', $course_id)->where('id', $lesson_id)->first();
        return view('delta_academy.attendance.attendance', compact('lessons_list'));
    }

    public function save_attendance_lesson(Request $request)
    {
        //dd( $request->all() );
        $course_id = $request->course_id;
        $lesson_id = $request->lesson_id;
        $user = User::where('email', $request->email)->first();
        if ($user) {
            //dd($course_id);
            $has_course_subscribed = DB::table('course_student')->where('course_id', $course_id)->where('user_id', $user->id);
            $has_course_subscribed = $has_course_subscribed->first();
            if ($has_course_subscribed) {
                $has_already_mark_present = AttendanceStudent::where('student_id', $user->id)
                    ->where('course_id', $course_id)
                    ->where('lesson_id', $lesson_id)
                    ->first();
                if ($has_already_mark_present) {
                    return redirect()->route('attendance.attendance.lesson', [$request->course_id, $request->lesson_id])->with('success', __('alerts.backend.general.already_mark_present'));
                } else {
                    $lesson_detail = Lesson::where('course_id', $course_id)->where('id', $lesson_id)->first();
                    $lesson_present_time = strtotime($lesson_detail->lesson_start_date . " +10 minutes");
                    //dd(date('Y-m-d H:i:s'));
                    //dd(date('Y-m-d H:i:s',$lesson_present_time));
                    if (strtotime(date('Y-m-d H:i:s')) < strtotime($lesson_detail->lesson_start_date . " -10 minutes")) {
                        if ($lesson_present_time >= strtotime(date('Y-m-d H:i:s')) && strtotime(date('Y-m-d H:i:s')) >= strtotime($lesson_detail->lesson_start_date . " -10 minutes")) {
                            $data = [
                                'student_id' => $user->id,
                                'course_id' => $course_id,
                                'lesson_id' => $lesson_id,
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                            AttendanceStudent::create($data);
                            return redirect()->route('attendance.attendance.lesson', [$request->course_id, $request->lesson_id])->with('success', __('alerts.backend.general.mark_attendance'));
                        } else {
                            return redirect()->route('attendance.attendance.lesson', [$request->course_id, $request->lesson_id])->with('success', __('alerts.backend.general.not_yet_started_lesson'));
                        }
                    } else {
                        return redirect()->route('attendance.attendance.lesson', [$request->course_id, $request->lesson_id])->with('success', __('alerts.backend.general.you_are_late_for_this_lesson'));
                    }
                }
            } else {
                return redirect()->route('attendance.attendance.lesson', [$request->course_id, $request->lesson_id])->with('error', __('alerts.backend.general.couse_not_subscribed'));
            }
        } else {
            return redirect()->route('attendance.attendance.lesson', [$request->course_id, $request->lesson_id])->with('error', __('alerts.backend.general.couse_not_subscribed'));
        }
    }
}
