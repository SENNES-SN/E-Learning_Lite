<?php

namespace App\Http\Controllers;

use App\Services\GamificationCompletionStore;
use App\Services\GamificationService;
use App\Services\MoodleService;
use App\Services\NotificationReadStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class LoginController extends Controller
{
    public function __construct(
        protected MoodleService $moodleService,
        protected GamificationService $gamificationService,
        protected ?GamificationCompletionStore $gamificationCompletionStore = null,
        protected ?NotificationReadStore $notificationReadStore = null,
    ) {}

    public function showLoginForm()
    {
        if (session('logged_in')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        try {
            $tokenResponse = $this->moodleService->fetchToken(
                $request->username,
                $request->password,
                (string) config('moodle.service_name'),
            );
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'username' => $this->isInvalidCredentialsError($e)
                        ? 'Username atau password salah.'
                        : 'Layanan pembelajaran sedang mengalami gangguan. Silakan coba lagi.',
                ]);
        }

        if (! isset($tokenResponse['token'])) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Username atau password salah.']);
        }

        try {
            $moodleUser = $this->moodleServiceWithToken($tokenResponse['token'])
                ->getUserByUsername($request->username);
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Layanan pembelajaran sedang mengalami gangguan. Silakan coba lagi.']);
        }

        if (! $moodleUser || ! isset($moodleUser['id'])) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Username atau password salah.']);
        }

        session([
            'logged_in' => true,
            'username' => $request->username,
            'moodle_token' => $tokenResponse['token'],
            'moodle_user' => [
                'id' => $moodleUser['id'],
                'name' => $moodleUser['fullname'] ?? $request->username,
                'email' => $moodleUser['email'] ?? null,
                'moodle_username' => $request->username,
            ],
        ]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->flush();

        return redirect()->route('login');
    }

    public function dashboard(Request $request)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:80',
                "regex:/^[\\pL\\pN\\s.,:_\\-\\/()'&]+$/u",
            ],
        ], [
            'q.max' => 'Pencarian maksimal 80 karakter.',
            'q.regex' => 'Pencarian hanya boleh berisi teks biasa tanpa simbol berbahaya.',
        ]);

        $searchQuery = trim((string) ($validated['q'] ?? ''));
        $searchQuery = trim((string) preg_replace('/\s+/', ' ', $searchQuery));
        $username = session('username');
        $allUserMoodleCourses = $this->withCurrentUserCourseProgress(
            $this->moodleCoursesForUsername($username),
        );
        $moodleCourses = $allUserMoodleCourses;
        $discoverableCourses = [];
        $unreadNotificationCount = 0;
        $notificationSummaryError = null;
        $upcomingDeadlineEvents = [];
        $courseDeadlineEvents = [];

        if ($searchQuery !== '' && is_array($moodleCourses)) {
            $needle = mb_strtolower($searchQuery);
            $moodleCourses = array_values(array_filter($moodleCourses, function ($course) use ($needle): bool {
                foreach (
                    [
                        $course['fullname'] ?? null,
                        $course['shortname'] ?? null,
                        $course['summary'] ?? null,
                        $course['id'] ?? null,
                    ] as $value
                ) {
                    if ($value !== null && str_contains(mb_strtolower((string) $value), $needle)) {
                        return true;
                    }
                }

                return false;
            }));
            $discoverableCourses = $this->searchDiscoverableCourses($searchQuery, $allUserMoodleCourses);
        }

        try {
            $notificationEvents = $this->cachedNotificationEventsForCourses($allUserMoodleCourses);
            $unreadNotificationCount = $this->unreadNotificationCount($notificationEvents);
            $pendingDeadlineEvents = $this->withoutCompletedDeadlineEvents(
                $notificationEvents,
                $allUserMoodleCourses,
            );
            $upcomingDeadlineEvents = $this->upcomingDeadlineEvents($pendingDeadlineEvents, 5);
            $courseDeadlineEvents = $this->deadlineEventsByCourse($pendingDeadlineEvents);
        } catch (\Throwable $throwable) {
            $notificationSummaryError = $this->moodleUnavailableMessage($throwable);
        }

        return view('dashboard', [
            'baseUrl' => config('moodle.base_url'),
            'serviceName' => config('moodle.service_name'),
            'token' => session('moodle_token') ?: config('moodle.token'),
            'username' => $username,
            'user' => (object) (session('moodle_user') ?? []),
            'moodleCourses' => $moodleCourses,
            'discoverableCourses' => $discoverableCourses,
            'searchQuery' => $searchQuery,
            'unreadNotificationCount' => $unreadNotificationCount,
            'upcomingDeadlineEvents' => $upcomingDeadlineEvents,
            'courseDeadlineEvents' => $courseDeadlineEvents,
            'notificationSummaryError' => $notificationSummaryError,
            'activePage' => 'dashboard',
        ]);
    }

    public function notifications(Request $request)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $payload = $request->validate([
            'filter' => ['nullable', 'string', 'in:deadline,task'],
        ]);
        $activeFilter = $payload['filter'] ?? 'all';

        $courses = $this->cachedNotificationCoursesForCurrentUser();
        $courseIds = collect($courses)
            ->pluck('id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
        $events = [];
        $allEvents = [];
        $notificationError = null;
        $unreadEventKeys = [];
        $unreadNotificationCounts = ['all' => 0, 'deadline' => 0, 'task' => 0];
        $deadlineEventCount = 0;

        if ($courseIds !== []) {
            try {
                $allEvents = $this->cachedNotificationEventsForCourses($courses);
                $allEvents = array_map(function ($event): array {
                    if (! is_array($event)) {
                        return [];
                    }

                    $event['_notification_key'] = $this->notificationEventKey($event);

                    return $event;
                }, $allEvents);
                $deadlineEventCount = count($this->deadlineNotificationEvents($allEvents));
                $unreadEventKeys = $this->unreadNotificationKeys($allEvents);
                $unreadNotificationCounts = $this->unreadNotificationCounts($allEvents, $unreadEventKeys);
                $events = match ($activeFilter) {
                    'deadline' => $this->deadlineNotificationEvents($allEvents),
                    'task' => $this->taskNotificationEvents($allEvents),
                    default => $allEvents,
                };
                $this->markNotificationsAsRead($allEvents);
            } catch (\Throwable $throwable) {
                $notificationError = $this->moodleUnavailableMessage($throwable);
            }
        }

        return view('notifications', [
            'user' => (object) (session('moodle_user') ?? []),
            'username' => session('username'),
            'courses' => $courses,
            'events' => $events,
            'activeFilter' => $activeFilter,
            'deadlineEventCount' => $deadlineEventCount,
            'unreadEventKeys' => $unreadEventKeys,
            'unreadNotificationCount' => $unreadNotificationCounts['all'],
            'unreadNotificationCounts' => $unreadNotificationCounts,
            'notificationError' => $notificationError,
        ]);
    }

    public function notificationUnreadSummary(Request $request)
    {
        if (! session('logged_in')) {
            return response()->json([
                'message' => 'Sesi pengguna telah berakhir.',
            ], 401);
        }

        try {
            $courses = $this->cachedNotificationCoursesForCurrentUser();
            $events = $this->cachedNotificationEventsForCourses($courses);

            return response()->json([
                'unread' => $this->unreadNotificationCounts($events),
                'checked_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Notifikasi belum dapat diperbarui.',
            ], 503);
        }
    }

    public function profile()
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        return view('profile', [
            'user' => (object) (session('moodle_user') ?? []),
            'username' => session('username'),
        ]);
    }

    public function courseDetail(int $courseId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        if ($courseId <= 0) {
            return redirect()->route('dashboard')
                ->withErrors(['courseid' => 'ID mata kuliah tidak valid.']);
        }

        session()->put('active_course_id', $courseId);

        $course = null;
        $contents = [];
        $contentError = null;
        $courseProgress = null;
        $assignmentCompletionStatuses = [];

        try {
            $course = $this->activeMoodleService()->getCourse($courseId);
            $contents = $this->activeMoodleService()->getCourseContents($courseId);
            $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
            $recentItems = $this->activeMoodleService()->getRecentlyAccessedItems();

            $trackableMaterialTypes = [
                'resource',
                'page',
                'book',
                'folder',
                'url',
                'label',
            ];

            foreach (is_array($recentItems) ? $recentItems : [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $modname = strtolower((string) ($item['modname'] ?? ''));
                $recentCourseId = (int) ($item['courseid'] ?? 0);
                $cmid = (int) ($item['cmid'] ?? 0);

                if (
                    $recentCourseId <= 0 ||
                    $cmid <= 0 ||
                    $recentCourseId !== $courseId ||
                    ! in_array($modname, $trackableMaterialTypes, true)
                ) {
                    continue;
                }

                $this->rememberLocalActivityCompletion($courseId, $cmid);
            }
            $courseProgress = $this->currentUserCourseProgress($courseId, $contents);
            $assignments = $this->activeMoodleService()->getAssignments($courseId);

            foreach ($contents as $section) {
                foreach (($section['modules'] ?? []) as $module) {
                    if (($module['modname'] ?? '') !== 'assign') {
                        continue;
                    }

                    $assignment = $this->findAssignmentForModule(
                        $assignments,
                        $module,
                    );

                    if (empty($assignment['id'])) {
                        continue;
                    }

                    try {
                        $submissionStatus = $this->activeMoodleService()
                            ->getAssignmentSubmissionStatus((int) $assignment['id']);

                        $settings = $this->assignmentSubmissionSettings(
                            $assignment,
                            $submissionStatus
                        );

                        $assignmentCompletionStatuses[(int) $module['id']] =
                            (bool) $settings['is_submitted'];
                    } catch (\Throwable) {
                        $assignmentCompletionStatuses[(int) $module['id']] = false;
                    }
                }
            }
        } catch (\Throwable $throwable) {
            $contentError = $this->moodleUnavailableMessage($throwable);
        }

        return view('course_detail', [
            'courseId' => $courseId,
            'course' => $course,
            'contents' => is_array($contents) ? $contents : [],
            'contentError' => $contentError,
            'courseProgress' => $courseProgress,
            'assignmentCompletionStatuses' => $assignmentCompletionStatuses,
        ]);
    }

    public function courseAchievements(int $courseId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        if ($courseId <= 0) {
            return redirect()->route('dashboard')->withErrors(['courseid' => 'Mata kuliah tidak valid.']);
        }

        session()->put('active_course_id', $courseId);

        $course = null;
        $contents = [];
        $courseProgress = null;
        $achievementError = null;

        try {
            $course = $this->activeMoodleService()->getCourse($courseId);
            $contents = $this->activeMoodleService()->getCourseContents($courseId);
            $courseProgress = $this->currentUserCourseProgress($courseId, $contents);
        } catch (\Throwable) {
            $achievementError = 'Pencapaian belum dapat dimuat. Silakan coba lagi beberapa saat.';
        }

        $leaderboard = $this->achievementLeaderboard(
            $courseId,
            is_array($courseProgress) ? $courseProgress : [],
            $contents,
        );
        $statuses = is_array($courseProgress['statuses'] ?? null) ? $courseProgress['statuses'] : [];
        if ($statuses === [] && (int) ($courseProgress['total'] ?? 0) > 0) {
            $completed = max(0, (int) ($courseProgress['completed'] ?? 0));
            $total = max($completed, (int) ($courseProgress['total'] ?? 0));
            for ($index = 0; $index < $total; $index++) {
                $statuses[] = ['cmid' => $index + 1, 'state' => $index < $completed ? 1 : 0];
            }
        }

        $achievement = $this->gamificationService->achievementSummary(
            $statuses,
            $leaderboard['complete'] ? $leaderboard['current_rank'] : null,
        );

        return view('achievements', [
            'courseId' => $courseId,
            'course' => $course,
            'achievement' => $achievement,
            'leaderboard' => $leaderboard,
            'achievementError' => $achievementError,
        ]);
    }

    public function courseModule(int $courseId, int $moduleId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        if ($courseId <= 0 || $moduleId <= 0) {
            return redirect()->route('dashboard')->withErrors(['courseid' => 'Mata kuliah atau aktivitas tidak valid.']);
        }

        session()->put('active_course_id', $courseId);

        $course = null;
        $module = null;
        $section = null;
        $assignment = null;
        $quiz = null;
        $quizAttempts = [];
        $quizError = null;
        $submissionStatus = null;
        $assignmentSettings = null;
        $assignmentSubmissions = [];
        $assignmentGrade = null;
        $assignmentSubmissionError = null;
        $assignmentNoteError = null;
        $canGradeAssignment = false;
        $canPreviewQuizAsTeacher = false;
        $enrolledUsers = [];
        $contentError = null;
        $contents = null;
        $courseProgress = null;
        $materialDescription = '';
        $assignmentNote = null;
        $assignmentDescription = '';
        $assignmentInstructions = '';
        $assignmentAttachments = [];
        $assignments = null;
        $quizzes = null;

        try {
            $course = $this->activeMoodleService()->getCourse($courseId);
            $contents = $this->activeMoodleService()->getCourseContents($courseId);
            [$module, $section] = $this->findModuleInCourseContents(
                $contents,
                $moduleId,
            );

            if ($module && in_array(strtolower((string) ($module['modname'] ?? '')), ['resource', 'page', 'book', 'folder', 'url', 'assign', 'quiz'], true)) {
                $moduleType = strtolower((string) ($module['modname'] ?? ''));

                if ($moduleType === 'assign') {
                    $assignments = $this->activeMoodleService()->getAssignments($courseId);
                }

                if ($moduleType === 'quiz') {
                    $quizzes = $this->activeMoodleService()->getQuizzes($courseId);
                }

                if (in_array($moduleType, ['resource', 'page', 'book', 'folder', 'url'], true)) {
                    $materialDescription = $this->moodlePlainText($module['description'] ?? $module['intro'] ?? '');
                }

                if ($moduleType === 'resource') {
                    try {
                        $resource = $this->findResourceForModule(
                            $this->activeMoodleService()->getResources($courseId),
                            $module,
                        );
                        $resourceDescription = $this->moodlePlainText($resource['intro'] ?? '');
                        if ($resourceDescription !== '') {
                            $materialDescription = $resourceDescription;
                        }
                    } catch (\Throwable) {
                        // Keep the course-content description as a fallback.
                    }
                }

                $courseProgress = $this->currentUserCourseProgress(
                    $courseId,
                    $contents,
                    $assignments,
                    $quizzes
                );
            }

            if (($module['modname'] ?? null) === 'assign') {
                $assignment = $this->findAssignmentForModule(
                    $assignments,
                    $module,
                );
                $assignmentDescription = $this->moodlePlainText($assignment['intro'] ?? $module['description'] ?? '');
                $assignmentInstructions = $this->moodlePlainText($assignment['activity'] ?? '');
                $assignmentAttachments = $this->assignmentAttachments($assignment, $module);

                if (! empty($assignment['id'])) {
                    try {
                        $submissionStatus = $this->activeMoodleService()->getAssignmentSubmissionStatus((int) $assignment['id']);
                        $assignmentSettings = $this->assignmentSubmissionSettings($assignment, $submissionStatus);
                        $assignmentPageMode = strtolower((string) request()->query('mode', 'detail'));
                        $submissionId = $this->assignmentSubmissionId($submissionStatus);
                        if (in_array($assignmentPageMode, ['work', 'confirm'], true) && $submissionId !== null) {
                            try {
                                $commentResponse = $this->activeMoodleService()->getAssignmentSubmissionComments(
                                    $moduleId,
                                    $submissionId,
                                );
                                $assignmentNote = $this->latestAssignmentSubmissionComment(
                                    $commentResponse,
                                    (int) ((session('moodle_user') ?? [])['id'] ?? 0),
                                );
                            } catch (\Throwable $throwable) {
                                $assignmentNoteError = $this->moodleUnavailableMessage($throwable);
                            }
                        }
                        $assignmentGrade = $this->currentUserAssignmentGrade($courseId, $assignment, $submissionStatus);
                    } catch (\Throwable $throwable) {
                        $assignmentSubmissionError = $this->moodleUnavailableMessage($throwable);
                    }
                }
            }

            if (($module['modname'] ?? null) === 'quiz') {
                try {
                    $canPreviewQuizAsTeacher = $this->currentUserCanTeachCourse($courseId);
                    $quiz = $this->findQuizForModule(
                        $quizzes,
                        $module,
                    );
                    $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
                    if (! $canPreviewQuizAsTeacher && ! empty($quiz['id']) && $currentUserId > 0) {
                        $attemptResponse = $this->activeMoodleService()->getQuizUserAttempts((int) $quiz['id'], $currentUserId);
                        $quizAttempts = is_array($attemptResponse) && is_array($attemptResponse['attempts'] ?? null)
                            ? $attemptResponse['attempts']
                            : [];
                        $gradesData = $this->activeMoodleService()->getUserGrades(
                            $courseId,
                            $currentUserId
                        );
                    }
                } catch (\Throwable $throwable) {
                    $quizError = $this->moodleUnavailableMessage($throwable);
                }
            }
        } catch (\Throwable $throwable) {
            $contentError = $this->moodleUnavailableMessage($throwable);
        }

        if (! $module && ! $contentError) {
            $contentError = 'Materi atau aktivitas tidak ditemukan pada course ini.';
        }

        $viewData = [
            'courseId' => $courseId,
            'moduleId' => $moduleId,
            'course' => $course,
            'module' => $module,
            'section' => $section,
            'materialDescription' => $materialDescription,
            'assignment' => $assignment,
            'quiz' => $quiz,
            'quizAttempts' => $quizAttempts,
            'quizError' => $quizError,
            'submissionStatus' => $submissionStatus,
            'assignmentSettings' => $assignmentSettings,
            'assignmentNote' => $assignmentNote,
            'assignmentDescription' => $assignmentDescription,
            'assignmentInstructions' => $assignmentInstructions,
            'assignmentAttachments' => $assignmentAttachments,
            'assignmentSubmissions' => $assignmentSubmissions,
            'assignmentGrade' => $assignmentGrade,
            'assignmentSubmissionError' => $assignmentSubmissionError,
            'assignmentNoteError' => $assignmentNoteError,
            'canGradeAssignment' => $canGradeAssignment,
            'canPreviewQuizAsTeacher' => $canPreviewQuizAsTeacher,
            'assignmentWorkflowStates' => $this->assignmentWorkflowStates(),
            'contentError' => $contentError,
            'courseProgress' => $courseProgress,
            'moodleToken' => session('moodle_token') ?: config('moodle.token'),
        ];

        if ($module && in_array(strtolower((string) ($module['modname'] ?? '')), ['resource', 'page', 'book', 'folder', 'url'], true)) {
            return view('material_detail', $viewData);
        }

        if ($module && strtolower((string) ($module['modname'] ?? '')) === 'assign' && ! $canGradeAssignment) {
            return view('assignment_detail', $viewData);
        }

        if ($module && strtolower((string) ($module['modname'] ?? '')) === 'quiz' && ! $canPreviewQuizAsTeacher) {
            return view('quiz_detail', $viewData);
        }

        if ($module && in_array(strtolower((string) ($module['modname'] ?? '')), ['forum', 'chat'], true)) {
            return view('discussion_detail', $viewData);
        }

        return redirect()
            ->route('courses.show', ['courseId' => $courseId])
            ->withErrors(['activity' => 'Aktivitas ini belum dapat ditampilkan.']);
    }

    public function completeMaterial(int $courseId, int $moduleId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        if ($courseId <= 0 || $moduleId <= 0 || $currentUserId <= 0) {
            return redirect()->route('login')->withErrors([
                'material' => 'Sesi kamu telah berakhir. Silakan login kembali.',
            ]);
        }

        try {
            $moodle = $this->activeMoodleService();
            $contents = $moodle->getCourseContents($courseId);
            [$module] = $this->findModuleInCourseContents($contents, $moduleId);
            $moduleType = strtolower((string) ($module['modname'] ?? ''));

            if (! $module || ! in_array($moduleType, ['resource', 'page', 'book', 'folder', 'url'], true)) {
                return back()->withErrors(['material' => 'Materi yang ingin diselesaikan tidak ditemukan.']);
            }

            $remoteBeforeStatuses = [];
            try {
                $beforeResponse = $moodle->getActivityCompletionStatus($courseId, $currentUserId);
                $remoteBeforeStatuses = is_array($beforeResponse) && is_array($beforeResponse['statuses'] ?? null)
                    ? $beforeResponse['statuses']
                    : [];
            } catch (\Throwable) {
                // Materi Moodle dapat tidak menyediakan activity completion.
            }

            $beforeStatuses = $this->withCourseActivityStatuses($contents, $remoteBeforeStatuses);
            $beforeStatuses = $this->withLocalCompletionOverrides($courseId, $beforeStatuses);

            $remoteAfterStatuses = $remoteBeforeStatuses;
            $hasRemoteCompletion = collect($remoteBeforeStatuses)->contains(
                fn($status): bool => is_array($status)
                    && (int) ($status['cmid'] ?? $status['coursemoduleid'] ?? 0) === $moduleId,
            );

            if (! $this->gamificationService->moduleIsComplete($beforeStatuses, $moduleId) && $hasRemoteCompletion) {
                try {
                    $moodle->markActivityComplete($moduleId);
                    $afterResponse = $moodle->getActivityCompletionStatus($courseId, $currentUserId);
                    $remoteAfterStatuses = is_array($afterResponse) && is_array($afterResponse['statuses'] ?? null)
                        ? $afterResponse['statuses']
                        : $remoteBeforeStatuses;
                } catch (\Throwable) {
                    // E-Learning Lite tetap mencatat penyelesaian secara lokal.
                }
            }

            $afterStatuses = $this->withCourseActivityStatuses($contents, $remoteAfterStatuses);
            $this->rememberLocalActivityCompletion($courseId, $moduleId);
            $afterStatuses = $this->withLocalCompletionOverrides($courseId, $afterStatuses);

            if (! $this->gamificationService->moduleIsComplete($afterStatuses, $moduleId)) {
                $afterStatuses = $this->withCompletedActivityStatus($afterStatuses, $moduleId);
            }

            $feedback = $this->gamificationService->materialCompletionFeedback(
                $beforeStatuses,
                $afterStatuses,
                $moduleId,
            );

            return redirect()
                ->route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId])
                ->with('material_completion_feedback', $feedback);
        } catch (\Throwable $throwable) {
            return back()->withErrors([
                'material' => 'Status materi belum berhasil diperbarui. ' . $this->moodleUnavailableMessage($throwable),
            ]);
        }
    }

    public function previewMoodleFile(Request $request)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $payload = $request->validate([
            'url' => ['required', 'string', 'max:3000'],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        $url = $payload['url'];
        if (! $this->isAllowedMoodleFileUrl($url)) {
            abort(403, 'File ini tidak dapat dibuka.');
        }

        try {
            $http = Http::timeout(60);
            if (! (bool) config('moodle.verify_ssl', true)) {
                $http = $http->withoutVerifying();
            }

            $moodleResponse = $http->get($url)->throw();
            $filename = $this->safeDownloadFilename($payload['filename'] ?? basename(parse_url($url, PHP_URL_PATH) ?: 'preview'));

            return response()->json([
                'filename' => $filename,
                'content_type' => $this->previewContentType($filename, $moodleResponse->header('Content-Type')),
                'content' => base64_encode($moodleResponse->body()),
            ])->header('Cache-Control', 'no-store');
        } catch (\Throwable $throwable) {
            abort(502, 'File belum dapat dipreview. Silakan coba lagi beberapa saat.');
        }
    }

    public function downloadMoodleFile(Request $request)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $payload = $request->validate([
            'url' => ['required', 'string', 'max:3000'],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        $url = $payload['url'];
        if (! $this->isAllowedMoodleFileUrl($url)) {
            abort(403, 'File ini tidak dapat diunduh.');
        }

        try {
            $http = Http::timeout(60);
            if (! (bool) config('moodle.verify_ssl', true)) {
                $http = $http->withoutVerifying();
            }

            $moodleResponse = $http->get($url)->throw();
            $filename = $this->safeDownloadFilename($payload['filename'] ?? basename(parse_url($url, PHP_URL_PATH) ?: 'file'));
            $content = $moodleResponse->body();
            $contentType = $this->previewContentType($filename, $moodleResponse->header('Content-Type'));

            return response()->streamDownload(
                static function () use ($content): void {
                    echo $content;
                },
                $filename,
                [
                    'Content-Type' => $contentType,
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ],
            );
        } catch (\Throwable) {
            abort(502, 'File belum dapat diunduh. Silakan coba lagi beberapa saat.');
        }
    }

    public function startQuizAttempt(Request $request, int $courseId, int $moduleId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        try {
            [$module,, $quiz] = $this->resolveQuizModule($courseId, $moduleId);
            $previewMode = $request->input('mode') === 'preview' && $this->currentUserCanTeachCourse($courseId);
            $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
            if ($currentUserId <= 0) {
                return redirect()->route('login')->withErrors(['quiz' => 'Sesi kamu telah berakhir. Silakan login kembali.']);
            }

            $quizId = (int) ($quiz['id'] ?? 0);
            $now = time();
            if (! empty($quiz['timeopen']) && $now < (int) $quiz['timeopen']) {
                return back()->withErrors(['quiz' => 'Kuis belum dapat dikerjakan karena waktu akses belum dimulai.']);
            }

            if (! empty($quiz['timeclose']) && $now > (int) $quiz['timeclose']) {
                return back()
                    ->with('quiz_access_expired', true)
                    ->with('quiz_expired_at', (int) $quiz['timeclose'])
                    ->withErrors(['quiz' => 'Waktu akses kuis telah berakhir.']);
            }

            $attempt = $this->currentQuizAttempt($quizId, $currentUserId);
            if (! $attempt) {
                $startResponse = $this->activeMoodleService()->startQuizAttempt($quizId);
                $attempt = $startResponse['attempt'] ?? $startResponse;
            }

            $attemptId = (int) ($attempt['id'] ?? 0);
            if ($attemptId <= 0) {
                return back()->withErrors(['quiz' => 'Kuis belum dapat dimulai. Silakan coba lagi.']);
            }

            return redirect()->route('courses.modules.quiz.attempt', [
                'courseId' => $courseId,
                'moduleId' => $moduleId,
                'attemptId' => $attemptId,
                'mode' => $previewMode ? 'preview' : null,
            ]);
        } catch (\Throwable $throwable) {
            return back()->withErrors(['quiz' => 'Kuis belum dapat dimulai. ' . $this->moodleUnavailableMessage($throwable)]);
        }
    }

    public function showQuizAttempt(Request $request, int $courseId, int $moduleId, int $attemptId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $page = max(0, (int) $request->query('page', 0));
        $previewMode = $request->query('mode') === 'preview' && $this->currentUserCanTeachCourse($courseId);

        try {
            [$module, $section, $quiz] = $this->resolveQuizModule($courseId, $moduleId);
            $attemptData = $this->activeMoodleService()->getQuizAttemptData($attemptId, $page);
            try {
                $attemptSummary = $this->activeMoodleService()->getQuizAttemptSummary($attemptId);
            } catch (\Throwable) {
                $attemptSummary = [
                    'questions' => is_array($attemptData) ? ($attemptData['questions'] ?? []) : [],
                ];
            }
            $attempt = is_array($attemptData) && is_array($attemptData['attempt'] ?? null)
                ? $attemptData['attempt']
                : [];
            $attemptDeadline = $this->quizAttemptDeadline($quiz, $attempt);

            return view('moodle_quiz_attempt', [
                'courseId' => $courseId,
                'moduleId' => $moduleId,
                'attemptId' => $attemptId,
                'page' => $page,
                'module' => $module,
                'section' => $section,
                'quiz' => $quiz,
                'attemptData' => is_array($attemptData) ? $attemptData : [],
                'attemptSummary' => is_array($attemptSummary) ? $attemptSummary : [],
                'attemptDeadline' => $attemptDeadline,
                'accessExpired' => $attemptDeadline !== null && time() > $attemptDeadline,
                'previewMode' => $previewMode,
            ]);
        } catch (\Throwable $throwable) {
            return redirect()
                ->route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId])
                ->withErrors(['quiz' => 'Kuis belum dapat dibuka. ' . $this->moodleUnavailableMessage($throwable)]);
        }
    }

    public function submitQuizAttempt(Request $request, int $courseId, int $moduleId, int $attemptId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $payload = $request->validate([
            'quiz_payload' => ['required', 'string'],
            'finishattempt' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:0'],
            'mode' => ['nullable', 'string', 'in:preview'],
        ]);

        $previewMode = ($payload['mode'] ?? null) === 'preview' && $this->currentUserCanTeachCourse($courseId);

        $data = json_decode($payload['quiz_payload'], true);
        if (! is_array($data)) {
            return back()->withErrors(['quiz' => 'Jawaban kuis tidak valid. Silakan coba kirim ulang.']);
        }

        $moodleData = [];
        foreach ($data as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '' || in_array($name, ['_token', 'quiz_payload', 'finishattempt', 'page', 'mode'], true)) {
                continue;
            }

            $moodleData[] = [
                'name' => $name,
                'value' => (string) ($item['value'] ?? ''),
            ];
        }

        try {
            [,, $quiz] = $this->resolveQuizModule($courseId, $moduleId);
            $attemptData = $this->activeMoodleService()->getQuizAttemptData(
                $attemptId,
                max(0, (int) ($payload['page'] ?? 0)),
            );
            $attempt = is_array($attemptData) && is_array($attemptData['attempt'] ?? null)
                ? $attemptData['attempt']
                : [];
            $attemptDeadline = $this->quizAttemptDeadline($quiz, $attempt);
            if ($attemptDeadline !== null && time() > $attemptDeadline) {
                return back()
                    ->with('quiz_access_expired', true)
                    ->with('quiz_expired_at', $attemptDeadline)
                    ->withErrors(['quiz' => 'Waktu akses kuis telah berakhir. Jawaban tidak dapat dikirim.']);
            }

            $finishAttempt = (bool) ($payload['finishattempt'] ?? false);
            $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
            $beforeStatuses = [];
            if ($finishAttempt && ! $previewMode && $currentUserId > 0) {
                try {
                    $beforeCompletion = $this->activeMoodleService()->getActivityCompletionStatus($courseId, $currentUserId);
                    $beforeStatuses = is_array($beforeCompletion) && is_array($beforeCompletion['statuses'] ?? null)
                        ? $beforeCompletion['statuses']
                        : [];
                } catch (\Throwable) {
                    $beforeStatuses = [];
                }
            }

            $processResponse = $this->activeMoodleService()->processQuizAttempt(
                $attemptId,
                $moodleData,
                $finishAttempt,
            );

            if ($finishAttempt && $previewMode) {
                return redirect()
                    ->route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId])
                    ->with('success', 'Pratinjau kuis selesai.');
            }

            if ($finishAttempt) {
                $review = [];
                try {
                    $reviewResponse = $this->activeMoodleService()->getQuizAttemptReview($attemptId);
                    $review = is_array($reviewResponse) ? $reviewResponse : [];
                } catch (\Throwable) {
                    $review = is_array($processResponse) ? $processResponse : [];
                }

                $afterStatuses = $beforeStatuses;
                if ($currentUserId > 0) {
                    try {
                        $afterCompletion = $this->activeMoodleService()->getActivityCompletionStatus($courseId, $currentUserId);
                        $afterStatuses = is_array($afterCompletion) && is_array($afterCompletion['statuses'] ?? null)
                            ? $afterCompletion['statuses']
                            : $beforeStatuses;
                    } catch (\Throwable) {
                        $afterStatuses = $beforeStatuses;
                    }
                }

                if (! $this->gamificationService->moduleIsComplete($afterStatuses, $moduleId)) {
                    try {
                        $this->activeMoodleService()->markActivityComplete($moduleId);
                        $afterCompletion = $this->activeMoodleService()->getActivityCompletionStatus($courseId, $currentUserId);
                        $afterStatuses = is_array($afterCompletion) && is_array($afterCompletion['statuses'] ?? null)
                            ? $afterCompletion['statuses']
                            : $afterStatuses;
                    } catch (\Throwable) {
                        $afterStatuses = $this->withCompletedActivityStatus($afterStatuses, $moduleId);
                    }
                }

                if (! $this->gamificationService->moduleIsComplete($afterStatuses, $moduleId)) {
                    $afterStatuses = $this->withCompletedActivityStatus($afterStatuses, $moduleId);
                }

                $this->rememberLocalActivityCompletion($courseId, $moduleId);
                $feedback = $this->gamificationService->activityCompletionFeedback(
                    $beforeStatuses,
                    $afterStatuses,
                    $moduleId,
                );
                if ($beforeStatuses === []) {
                    $feedback['badge'] = null;
                }
                $feedback = array_merge($feedback, $this->quizReviewScore($quiz, $review));

                return redirect()
                    ->route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId])
                    ->with('quiz_completion_feedback', $feedback);
            }

            return redirect()
                ->route('courses.modules.quiz.attempt', [
                    'courseId' => $courseId,
                    'moduleId' => $moduleId,
                    'attemptId' => $attemptId,
                    'page' => (int) ($payload['page'] ?? 0),
                    'mode' => $previewMode ? 'preview' : null,
                ])
                ->with('success', $previewMode
                    ? 'Pratinjau kuis diperbarui.'
                    : 'Jawaban kuis berhasil disimpan.');
        } catch (\Throwable $throwable) {
            return back()->withErrors(['quiz' => 'Jawaban kuis belum berhasil dikirim. ' . $this->moodleUnavailableMessage($throwable)]);
        }
    }

    public function selfEnrollCourse(Request $request, int $courseId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        if ($courseId <= 0) {
            return back()->withErrors(['password' => 'ID kursus tidak valid.']);
        }

        $payload = $request->validate([
            'password' => ['required', 'string', 'max:120'],
        ], [
            'password.required' => 'Password course wajib diisi.',
        ]);

        $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        if ($currentUserId <= 0) {
            return redirect()->route('dashboard')->withErrors(['password' => 'Sesi akun tidak ditemukan. Silakan login kembali.']);
        }

        try {
            $this->activeMoodleService()->selfEnrollIntoCourse(
                $currentUserId,
                $courseId,
                $payload['password'],
            );

            return redirect()
                ->route('courses.show', ['courseId' => $courseId])
                ->with('success', 'Berhasil mendaftar ke kursus.');
        } catch (\InvalidArgumentException $throwable) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['password' => $throwable->getMessage()]);
        } catch (\Throwable $throwable) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['password' => 'Gagal mendaftar ke kursus: ' . $this->moodleUnavailableMessage($throwable)]);
        }
    }

    protected function isAllowedMoodleFileUrl(string $url): bool
    {
        $baseHost = parse_url((string) config('moodle.base_url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);
        $urlPath = parse_url($url, PHP_URL_PATH) ?: '';

        if (! $baseHost || ! $urlHost || strtolower($baseHost) !== strtolower($urlHost)) {
            return false;
        }

        return str_contains($urlPath, '/pluginfile.php')
            || str_contains($urlPath, '/webservice/pluginfile.php');
    }

    protected function safeDownloadFilename(string $filename): string
    {
        $filename = trim(basename($filename));
        $filename = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename) ?: 'preview';

        return $filename !== '' ? $filename : 'preview';
    }

    protected function previewContentType(string $filename, ?string $fallback = null): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'txt', 'log' => 'text/plain; charset=UTF-8',
            'csv' => 'text/csv; charset=UTF-8',
            default => $fallback ?: 'application/octet-stream',
        };
    }

    public function submitAssignment(Request $request, int $courseId, int $moduleId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        try {
            [$module] = $this->findModuleInCourseContents(
                $this->activeMoodleService()->getCourseContents($courseId),
                $moduleId,
            );

            if (! $module || ($module['modname'] ?? null) !== 'assign') {
                return back()->withErrors(['answer' => 'Aktivitas ini bukan tugas yang dapat dikumpulkan.']);
            }

            $assignment = $this->findAssignmentForModule(
                $this->activeMoodleService()->getAssignments($courseId),
                $module,
            );

            if (empty($assignment['id'])) {
                return back()->withErrors(['answer' => 'Detail tugas tidak ditemukan.']);
            }

            if ($this->currentUserCanTeachCourse($courseId)) {
                return back()->withErrors(['answer' => 'Akun dosen menggunakan halaman ini untuk melihat submission dan memberi nilai, bukan mengirim jawaban tugas.']);
            }

            $submissionStatus = $this->activeMoodleService()->getAssignmentSubmissionStatus((int) $assignment['id']);
            $assignmentSettings = $this->assignmentSubmissionSettings($assignment, $submissionStatus);

            if ($this->assignmentDeadlineHasPassed($assignment)) {
                return back()
                    ->withInput()
                    ->with('assignment_deadline_expired', true)
                    ->withErrors(['answer' => 'Waktu pengumpulan telah berakhir. Tugas tidak dapat dikerjakan atau dikumpulkan.']);
            }

            if ($assignmentSettings['is_submitted']) {
                return back()->withErrors(['answer' => 'Jawaban sudah dikumpulkan dan tidak dapat diubah atau dikirim ulang.']);
            }

            $maxUploadKilobytes = max(1, (int) ceil($assignmentSettings['max_file_size'] / 1024));
            $replaceFiles = $request->boolean('replace_files');
            $uploadLimit = $replaceFiles
                ? $assignmentSettings['max_files']
                : $assignmentSettings['remaining_files'];

            $payload = $request->validate([
                'note' => ['nullable', 'string', 'max:10000'],
                'replace_files' => ['nullable', 'boolean'],
                'answer_files' => ['nullable', 'array', 'max:' . $uploadLimit],
                'answer_files.*' => ['nullable', 'file', 'max:' . $maxUploadKilobytes],
            ], [
                'note.max' => 'Catatan maksimal 10.000 karakter.',
                'answer_files.array' => 'Lampiran jawaban harus berupa daftar file.',
                'answer_files.max' => $replaceFiles
                    ? 'Jumlah file pengganti maksimal ' . $assignmentSettings['max_files'] . ' file.'
                    : 'Sisa slot upload hanya ' . $assignmentSettings['remaining_files'] . ' file.',
                'answer_files.*.file' => 'Lampiran jawaban harus berupa file.',
                'answer_files.*.max' => 'Ukuran tiap file jawaban maksimal ' . $assignmentSettings['max_file_size_label'] . '.',
            ]);

            $note = trim((string) ($payload['note'] ?? ''));
            $answerFiles = $request->file('answer_files', []);

            if ($answerFiles !== [] && ! $assignmentSettings['file_enabled']) {
                return back()
                    ->withInput()
                    ->withErrors(['answer_files' => 'Tugas ini tidak menerima lampiran file.']);
            }

            if ($answerFiles !== [] && ! $replaceFiles && $assignmentSettings['remaining_files'] <= 0) {
                return back()
                    ->withInput()
                    ->withErrors(['answer_files' => 'Batas jumlah file sudah tercapai. Hapus atau ganti file yang salah terlebih dahulu.']);
            }

            if ($assignmentSettings['submission_files'] === [] && $answerFiles === []) {
                return back()
                    ->withInput()
                    ->withErrors(['answer_files' => 'Upload file jawaban terlebih dahulu.']);
            }

            foreach ($answerFiles as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                if ($assignmentSettings['accepted_extensions'] !== [] && ! in_array('.' . $extension, $assignmentSettings['accepted_extensions'], true)) {
                    return back()
                        ->withInput()
                        ->withErrors(['answer_files' => 'Format file .' . $extension . ' tidak diterima. Format yang diizinkan: ' . $assignmentSettings['accepted_types_label'] . '.']);
                }
            }

            $draftItemId = null;
            if ($answerFiles !== [] && ! $replaceFiles && $assignmentSettings['submission_files'] !== []) {
                $draftItemId = $this->reuploadSubmissionFilesToDraft($assignmentSettings['submission_files']);
            }

            foreach ($answerFiles as $file) {
                $uploadResponse = $this->activeMoodleService()->uploadDraftFile($file, $draftItemId);
                $firstUpload = $uploadResponse[0] ?? $uploadResponse;
                $draftItemId = isset($firstUpload['itemid']) ? (int) $firstUpload['itemid'] : $draftItemId;

                if (! $draftItemId) {
                    return back()
                        ->withInput()
                        ->withErrors(['answer_files' => 'File belum berhasil disiapkan. Silakan unggah kembali.']);
                }
            }

            if ($draftItemId !== null) {
                $this->activeMoodleService()->saveAssignmentSubmission(
                    (int) $assignment['id'],
                    null,
                    $draftItemId,
                );
                $submissionStatus = $this->activeMoodleService()->getAssignmentSubmissionStatus((int) $assignment['id']);
            }

            if ($note !== '') {
                $submissionId = $this->assignmentSubmissionId($submissionStatus);
                if ($submissionId === null) {
                    return back()
                        ->withInput()
                        ->withErrors(['note' => 'Catatan belum dapat disimpan karena data pengajuan tugas belum tersedia.']);
                }

                $this->activeMoodleService()->addAssignmentSubmissionComment($moduleId, $submissionId, $note);
            }

            return redirect()
                ->to(route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId]) . '?mode=confirm')
                ->with('success', 'Jawaban tugas berhasil disiapkan. Periksa kembali sebelum mengumpulkan.');
        } catch (\Throwable $throwable) {
            return back()
                ->withInput()
                ->withErrors(['note' => 'File atau catatan belum berhasil disiapkan. ' . $this->moodleUnavailableMessage($throwable)]);
        }
    }

    public function deleteAssignmentDraft(Request $request, int $courseId, int $moduleId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $payload = $request->validate([
            'filename' => ['required', 'string'],
            'filepath' => ['nullable', 'string'],
        ]);

        try {
            [$module] = $this->findModuleInCourseContents(
                $this->activeMoodleService()->getCourseContents($courseId),
                $moduleId,
            );

            if (! $module || ($module['modname'] ?? null) !== 'assign') {
                return back()->withErrors(['delete_draft' => 'Aktivitas ini bukan tugas yang dapat diubah.']);
            }

            $assignment = $this->findAssignmentForModule(
                $this->activeMoodleService()->getAssignments($courseId),
                $module,
            );

            if (empty($assignment['id'])) {
                return back()->withErrors(['delete_draft' => 'Detail tugas tidak ditemukan.']);
            }

            if ($this->currentUserCanTeachCourse($courseId)) {
                return back()->withErrors(['delete_draft' => 'Akun dosen tidak mengelola draft jawaban mahasiswa dari form submit Lite.']);
            }

            $submissionStatus = $this->activeMoodleService()->getAssignmentSubmissionStatus((int) $assignment['id']);
            $assignmentSettings = $this->assignmentSubmissionSettings($assignment, $submissionStatus);
            if ($assignmentSettings['is_submitted']) {
                return back()->withErrors(['delete_draft' => 'Jawaban sudah final submit, sehingga draft tidak bisa dihapus dari Lite.']);
            }

            if (count($assignmentSettings['submission_files']) <= 1) {
                return back()->withErrors([
                    'delete_draft' => 'File terakhir tidak dapat langsung dihapus. Gunakan mode ganti file untuk menggantinya.',
                ]);
            }

            $deleteFilename = $payload['filename'];
            $deleteFilepath = $payload['filepath'] ?? '/';
            $remainingFiles = array_values(array_filter(
                $assignmentSettings['submission_files'],
                fn($file): bool => ! (
                    ($file['filename'] ?? '') === $deleteFilename
                    && ($file['filepath'] ?? '/') === $deleteFilepath
                ),
            ));

            if (count($remainingFiles) === count($assignmentSettings['submission_files'])) {
                return back()->withErrors(['delete_draft' => 'File draft yang dipilih tidak ditemukan.']);
            }

            $draftItemId = $this->reuploadSubmissionFilesToDraft($remainingFiles);

            $this->activeMoodleService()->saveAssignmentSubmission(
                (int) $assignment['id'],
                $this->submissionOnlineText($submissionStatus),
                $draftItemId,
            );

            $updatedStatus = $this->activeMoodleService()->getAssignmentSubmissionStatus((int) $assignment['id']);
            $stillExists = collect($this->submissionFiles($updatedStatus))->contains(
                fn($file): bool => ($file['filename'] ?? '') === $deleteFilename
                    && ($file['filepath'] ?? '/') === $deleteFilepath,
            );

            if ($stillExists) {
                return back()->withErrors(['delete_draft' => 'File belum dapat dihapus. Silakan gunakan mode ganti file atau coba lagi.']);
            }

            return redirect()
                ->route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId])
                ->with('success', 'File berhasil dihapus.');
        } catch (\Throwable $throwable) {
            return back()->withErrors(['delete_draft' => 'File belum berhasil dihapus. ' . $this->moodleUnavailableMessage($throwable)]);
        }
    }

    public function finalSubmitAssignment(Request $request, int $courseId, int $moduleId)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $payload = $request->validate([
            'accept_statement' => ['nullable', 'boolean'],
        ]);

        try {
            [$module] = $this->findModuleInCourseContents(
                $this->activeMoodleService()->getCourseContents($courseId),
                $moduleId,
            );

            if (! $module || ($module['modname'] ?? null) !== 'assign') {
                return back()->withErrors(['final_submit' => 'Aktivitas ini bukan tugas yang dapat dikumpulkan.']);
            }

            $assignment = $this->findAssignmentForModule(
                $this->activeMoodleService()->getAssignments($courseId),
                $module,
            );

            if (empty($assignment['id'])) {
                return back()->withErrors(['final_submit' => 'Detail tugas tidak ditemukan.']);
            }

            if ($this->currentUserCanTeachCourse($courseId)) {
                return back()->withErrors(['final_submit' => 'Akun dosen menggunakan halaman ini untuk memberi nilai, bukan final submit jawaban.']);
            }

            $submissionStatus = $this->activeMoodleService()->getAssignmentSubmissionStatus((int) $assignment['id']);
            $assignmentSettings = $this->assignmentSubmissionSettings($assignment, $submissionStatus);
            if ($assignmentSettings['is_submitted']) {
                return back()->withErrors(['final_submit' => 'Jawaban sudah dikumpulkan.']);
            }

            if ($this->assignmentDeadlineHasPassed($assignment)) {
                return back()
                    ->with('assignment_deadline_expired', true)
                    ->withErrors(['final_submit' => 'Waktu pengumpulan telah berakhir. Tugas tidak dapat dikumpulkan.']);
            }

            if ($assignmentSettings['submission_files'] === []) {
                return back()->withErrors(['final_submit' => 'Upload file jawaban sebelum mengumpulkan tugas.']);
            }

            $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
            $beforeStatuses = [];
            if ($currentUserId > 0) {
                try {
                    $beforeCompletion = $this->activeMoodleService()->getActivityCompletionStatus($courseId, $currentUserId);
                    $beforeStatuses = is_array($beforeCompletion) && is_array($beforeCompletion['statuses'] ?? null)
                        ? $beforeCompletion['statuses']
                        : [];
                } catch (\Throwable) {
                    $beforeStatuses = [];
                }
            }

            $this->activeMoodleService()->submitAssignmentForGrading(
                (int) $assignment['id'],
                (bool) ($payload['accept_statement'] ?? false),
            );

            $confirmedStatus = $this->activeMoodleService()->getAssignmentSubmissionStatus((int) $assignment['id']);
            if (! $this->assignmentSubmissionSettings($assignment, $confirmedStatus)['is_submitted']) {
                return back()->withErrors(['final_submit' => 'Pengumpulan belum terkonfirmasi. Silakan periksa jawaban dan coba lagi.']);
            }

            $this->rememberLocalActivityCompletion($courseId, $moduleId);

            $afterStatuses = $beforeStatuses;
            if ($currentUserId > 0) {
                try {
                    $afterCompletion = $this->activeMoodleService()->getActivityCompletionStatus($courseId, $currentUserId);
                    $afterStatuses = is_array($afterCompletion) && is_array($afterCompletion['statuses'] ?? null)
                        ? $afterCompletion['statuses']
                        : $beforeStatuses;
                } catch (\Throwable) {
                    $afterStatuses = $beforeStatuses;
                }
            }

            if (! $this->gamificationService->moduleIsComplete($afterStatuses, $moduleId)) {
                try {
                    $this->activeMoodleService()->markActivityComplete($moduleId);
                    $afterCompletion = $this->activeMoodleService()->getActivityCompletionStatus($courseId, $currentUserId);
                    $afterStatuses = is_array($afterCompletion) && is_array($afterCompletion['statuses'] ?? null)
                        ? $afterCompletion['statuses']
                        : $afterStatuses;
                } catch (\Throwable) {
                    $afterStatuses = $this->withCompletedActivityStatus($afterStatuses, $moduleId);
                }
            }

            if (! $this->gamificationService->moduleIsComplete($afterStatuses, $moduleId)) {
                $afterStatuses = $this->withCompletedActivityStatus($afterStatuses, $moduleId);
            }

            $feedback = $this->gamificationService->activityCompletionFeedback(
                $beforeStatuses,
                $afterStatuses,
                $moduleId,
            );
            if ($beforeStatuses === []) {
                $feedback['badge'] = null;
            }

            return redirect()
                ->to(route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId]) . '?mode=confirm')
                ->with('assignment_completion_feedback', $feedback);
        } catch (\Throwable $throwable) {
            return back()->withErrors(['final_submit' => 'Jawaban belum berhasil dikumpulkan. ' . $this->moodleUnavailableMessage($throwable)]);
        }
    }



    public function grades(Request $request)
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        $courseId = (int) $request->query('courseid', 1);

        if ($courseId <= 0) {
            return redirect()->route('dashboard')
                ->withErrors(['courseid' => 'ID mata kuliah tidak valid.']);
        }

        session()->put('active_course_id', $courseId);

        $course = null;
        $gradesData = [];
        $gradeError = null;

        $sessionUser = session('moodle_user', []);
        $userId = is_array($sessionUser)
            ? (int) ($sessionUser['id'] ?? 0)
            : 0;

        if ($userId <= 0) {
            return redirect()->route('login');
        }

        try {
            $course = $this->activeMoodleService()->getCourse($courseId);
        } catch (\Throwable) {
            // Nama mata kuliah menggunakan teks cadangan jika layanan belum dapat diakses.
        }

        try {
            $gradesData = $this->activeMoodleService()
                ->getUserGrades($courseId, $userId);
        } catch (\Throwable) {
            $gradeError = 'Nilai belum dapat dimuat. Silakan coba lagi beberapa saat.';
        }

        return view('grades', [
            'courseId' => $courseId,
            'course' => $course,
            'gradesData' => $gradesData,
            'gradeRows' => $this->studentGradeRows(
                $gradesData,
                $this->studentAssignmentSubmissionData($gradesData),
            ),
            'gradeError' => $gradeError,
        ]);
    }

    /**
     * @return array{tasks: array<int, array<string, mixed>>, quizzes: array<int, array<string, mixed>>}
     */
    protected function studentGradeRows(mixed $gradesData, array $assignmentSubmissions = []): array
    {
        $rows = ['tasks' => [], 'quizzes' => []];
        if (! is_array($gradesData)) {
            return $rows;
        }

        $userGrades = is_array($gradesData['usergrades'] ?? null) ? $gradesData['usergrades'] : [];
        $sessionUser = session('moodle_user', []);
        $currentUserId = (int) (is_array($sessionUser) ? ($sessionUser['id'] ?? 0) : 0);
        $currentUserGrade = collect($userGrades)->first(
            fn($grade): bool => is_array($grade) && (int) ($grade['userid'] ?? 0) === $currentUserId,
        );
        if (! is_array($currentUserGrade)) {
            $currentUserGrade = collect($userGrades)->first(fn($grade): bool => is_array($grade));
        }

        $gradeItems = is_array($currentUserGrade['gradeitems'] ?? null)
            ? $currentUserGrade['gradeitems']
            : (is_array($gradesData['gradeitems'] ?? null) ? $gradesData['gradeitems'] : []);

        foreach ($gradeItems as $item) {
            if (! is_array($item) || $this->gradeItemIsHidden($item)) {
                continue;
            }

            $itemType = strtolower((string) ($item['itemtype'] ?? ''));
            if (in_array($itemType, ['course', 'category'], true)) {
                continue;
            }

            $name = trim((string) ($item['itemname'] ?? ''));
            $module = strtolower(trim((string) ($item['itemmodule'] ?? '')));
            $group = match ($module) {
                'assign', 'assignment' => 'tasks',
                'quiz' => 'quizzes',
                default => null,
            };

            if ($group === null && preg_match('/\b(quiz|kuis)\b/iu', $name) === 1) {
                $group = 'quizzes';
            } elseif ($group === null && preg_match('/\b(tugas|assignment)\b/iu', $name) === 1) {
                $group = 'tasks';
            }

            if ($group === null) {
                continue;
            }

            $gradeText = $this->firstFilledString(
                $item['graderaw'] ?? null,
                $item['grade'] ?? null,
                $item['gradeformatted'] ?? null,
                $item['gradefordisplay'] ?? null,
                $item['str_grade'] ?? null,
            );
            if (! $this->hasVisibleGrade($gradeText)) {
                $gradeText = null;
            }

            $submittedAt = null;
            foreach (['gradedatesubmitted', 'datesubmitted', 'timesubmitted', 'submissiontime', 'timefinish'] as $dateField) {
                $candidate = (int) ($item[$dateField] ?? 0);
                if ($candidate > 0) {
                    $submittedAt = $candidate;
                    break;
                }
            }

            $assignmentSubmission = $group === 'tasks'
                ? ($assignmentSubmissions[(int) ($item['iteminstance'] ?? 0)] ?? null)
                : null;
            if (is_array($assignmentSubmission) && ! (bool) ($assignmentSubmission['is_submitted'] ?? false)) {
                continue;
            }
            if ($group === 'tasks' && ! is_array($assignmentSubmission) && $gradeText === null) {
                continue;
            }

            if (is_array($assignmentSubmission) && $submittedAt === null) {
                $submissionTimestamp = (int) ($assignmentSubmission['submitted_at'] ?? 0);
                $submittedAt = $submissionTimestamp > 0 ? $submissionTimestamp : null;
            }

            $hasSubmittedAssignment = is_array($assignmentSubmission)
                && (bool) ($assignmentSubmission['is_submitted'] ?? false);
            if ($gradeText === null && $submittedAt === null && ! $hasSubmittedAssignment) {
                continue;
            }

            $rows[$group][] = [
                'id' => $item['id'] ?? $item['cmid'] ?? count($rows[$group]) + 1,
                'name' => $name !== '' ? $name : ($group === 'tasks' ? 'Tugas' : 'Quiz'),
                'submitted_at' => $submittedAt,
                'grade' => $this->numericGradeValue($gradeText),
                'grade_text' => $gradeText,
                'max_grade' => $this->numericGradeValue($item['grademax'] ?? null),
                'graded' => $gradeText !== null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{is_submitted: bool, submitted_at: ?int}>
     */
    protected function studentAssignmentSubmissionData(mixed $gradesData): array
    {
        if (! is_array($gradesData)) {
            return [];
        }

        $submissions = [];
        foreach (($gradesData['usergrades'] ?? []) as $userGrade) {
            if (! is_array($userGrade)) {
                continue;
            }

            foreach (($userGrade['gradeitems'] ?? []) as $item) {
                $module = strtolower(trim((string) ($item['itemmodule'] ?? '')));
                $assignmentId = (int) ($item['iteminstance'] ?? 0);
                if (! in_array($module, ['assign', 'assignment'], true) || $assignmentId <= 0 || isset($submissions[$assignmentId])) {
                    continue;
                }

                try {
                    $status = $this->activeMoodleService()->getAssignmentSubmissionStatus($assignmentId);
                    $lastAttempt = is_array($status) && is_array($status['lastattempt'] ?? null)
                        ? $status['lastattempt']
                        : [];
                    $submission = is_array($lastAttempt['submission'] ?? null)
                        ? $lastAttempt['submission']
                        : [];
                    $submissionStatus = strtolower((string) ($submission['status'] ?? ''));
                    if ($submissionStatus === '') {
                        continue;
                    }

                    $isSubmitted = $submissionStatus === 'submitted';
                    $submittedAt = null;

                    foreach (['timemodified', 'timecreated', 'timesubmitted'] as $dateField) {
                        $candidate = (int) ($submission[$dateField] ?? 0);
                        if ($candidate > 0) {
                            $submittedAt = $candidate;
                            break;
                        }
                    }

                    $submissions[$assignmentId] = [
                        'is_submitted' => $isSubmitted,
                        'submitted_at' => $submittedAt,
                    ];
                } catch (\Throwable) {
                    // Grade item tetap digunakan jika status submission tidak dapat dibaca.
                }
            }
        }

        return $submissions;
    }

    protected function activeMoodleService(): MoodleService
    {
        if (app()->bound(MoodleService::class)) {
            return app(MoodleService::class);
        }

        $token = session('moodle_token') ?: config('moodle.token');
        $baseUrl = (string) config('moodle.base_url');

        return app(MoodleService::class, [
            'token' => $token,
            'baseUrl' => $baseUrl,
        ]);
    }

    protected function moodleServiceWithToken(string $token): MoodleService
    {
        if (app()->bound(MoodleService::class)) {
            return app(MoodleService::class);
        }

        return app(MoodleService::class, [
            'token' => $token,
            'baseUrl' => (string) config('moodle.base_url'),
        ]);
    }

    protected function moodleUnavailableMessage(\Throwable $throwable): string
    {
        $message = $throwable->getMessage();

        if (preg_match('/HTTP request failed!\s+HTTP\/[0-9.]+\s+([0-9]{3})\s+([^\r\n]+)/i', $message, $matches)) {
            return 'Layanan pembelajaran sedang tidak dapat diakses. Silakan coba lagi beberapa saat.';
        }

        if (str_contains(strtolower($message), 'timed out') || str_contains(strtolower($message), 'timeout')) {
            return 'Layanan pembelajaran sedang lambat atau tidak merespons. Silakan coba lagi beberapa saat.';
        }

        if (str_contains(strtolower($message), 'failed to open stream')) {
            return 'Layanan pembelajaran sedang tidak dapat diakses. Silakan coba lagi beberapa saat.';
        }

        return 'Layanan pembelajaran sedang mengalami gangguan. Silakan coba lagi beberapa saat.';
    }

    protected function moodlePlainText(mixed $value): string
    {
        $html = (string) $value;
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*li\b[^>]*>/i', '- ', $html) ?? $html;
        $html = preg_replace('/<\s*\/\s*(p|div|li|h[1-6])\s*>/i', "\n", $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected function isInvalidCredentialsError(\Throwable $throwable): bool
    {
        $message = mb_strtolower($throwable->getMessage());

        return str_contains($message, 'invalid login')
            || str_contains($message, 'invalid username')
            || str_contains($message, 'username or password')
            || str_contains($message, 'username atau password')
            || str_contains($message, 'login tidak valid');
    }

    protected function moodleCoursesForUsername(?string $username): array
    {
        if (! $username) {
            return [];
        }

        try {
            $moodleUser = $this->activeMoodleService()->getUserByUsername($username);
            if (! $moodleUser || ! isset($moodleUser['id'])) {
                return [];
            }

            $courses = $this->activeMoodleService()->getUserCourses((int) $moodleUser['id']);

            return is_array($courses) ? $courses : [];
        } catch (\Throwable) {
            return [];
        }
    }



    protected function withCurrentUserCourseProgress(mixed $courses): array
    {
        if (! is_array($courses)) {
            return [];
        }

        return array_map(function ($course): array {
            $course = is_array($course) ? $course : [];
            $courseId = (int) ($course['id'] ?? 0);
            $course['progress'] = $courseId > 0 ? $this->currentUserCourseProgress($courseId) : null;

            return $course;
        }, $courses);
    }

    protected function currentUserCourseProgress(
        int $courseId,
        mixed $contents = null,
        mixed $assignments = null,
        mixed $quizzes = null
    ): ?array {
        $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        if ($courseId <= 0 || $currentUserId <= 0) {
            return null;
        }

        $localOverrides = $this->localActivityCompletionOverrides($courseId);
        if ($contents === null && $localOverrides !== []) {
            try {
                $contents = $this->activeMoodleService()->getCourseContents($courseId);
            } catch (\Throwable) {
                $contents = null;
            }
        }

        try {
            $completion = $this->activeMoodleService()->getActivityCompletionStatus($courseId, $currentUserId);
            $statuses = is_array($completion) ? ($completion['statuses'] ?? []) : [];
            $statuses = $this->withCourseActivityStatuses($contents, is_array($statuses) ? $statuses : []);

            try {
                if ($assignments === null) {
                    $assignments = $this->activeMoodleService()->getAssignments($courseId);
                }

                foreach ($contents as $section) {
                    foreach (($section['modules'] ?? []) as $module) {
                        if (($module['modname'] ?? '') !== 'assign') {
                            continue;
                        }

                        $assignment = $this->findAssignmentForModule(
                            $assignments,
                            $module,
                        );

                        if (empty($assignment['id'])) {
                            continue;
                        }

                        try {
                            $submissionStatus = $this->activeMoodleService()
                                ->getAssignmentSubmissionStatus((int) $assignment['id']);

                            $settings = $this->assignmentSubmissionSettings(
                                $assignment,
                                $submissionStatus
                            );

                            if ($settings['is_submitted']) {
                                $statuses = $this->withCompletedActivityStatus(
                                    $statuses,
                                    (int) $module['id']
                                );
                            }
                        } catch (\Throwable) {
                            continue;
                        }
                    }
                }
            } catch (\Throwable) {
                // Jika data submission tidak tersedia, gunakan completion Moodle seperti biasa.
            }

            try {
                if ($quizzes === null) {
                    $quizzes = $this->activeMoodleService()->getQuizzes($courseId);
                }

                foreach ($contents as $section) {
                    foreach (($section['modules'] ?? []) as $module) {
                        if (($module['modname'] ?? '') !== 'quiz') {
                            continue;
                        }

                        $quiz = $this->findQuizForModule(
                            $quizzes,
                            $module,
                        );

                        if (empty($quiz['id'])) {
                            continue;
                        }

                        try {
                            $attemptResponse = $this->activeMoodleService()
                                ->getQuizUserAttempts(
                                    (int) $quiz['id'],
                                    $currentUserId,
                                    'all'
                                );

                            $attempts = is_array($attemptResponse)
                                ? ($attemptResponse['attempts'] ?? [])
                                : [];

                            $hasFinishedAttempt = collect($attempts)->contains(
                                fn($attempt): bool =>
                                is_array($attempt)
                                    && strtolower((string) ($attempt['state'] ?? '')) === 'finished'
                            );

                            if ($hasFinishedAttempt) {
                                $statuses = $this->withCompletedActivityStatus(
                                    $statuses,
                                    (int) $module['id']
                                );
                            }
                        } catch (\Throwable) {
                            continue;
                        }
                    }
                }
            } catch (\Throwable) {
                // Jika data attempt kuis tidak tersedia,gunakan completion Moodle seperti biasa.
            }
            try {
                $recentResponse = $this->activeMoodleService()->getRecentlyAccessedItems();

                $recentItems = is_array($recentResponse)
                    ? $recentResponse
                    : [];
                $accessedCmids = collect($recentItems)
                    ->filter(
                        fn($item): bool =>
                        is_array($item)
                            && (int) ($item['courseid'] ?? 0) === $courseId
                            && (int) ($item['userid'] ?? 0) === $currentUserId
                    )
                    ->pluck('cmid')
                    ->map(fn($cmid): int => (int) $cmid)
                    ->filter(fn($cmid): bool => $cmid > 0)
                    ->unique()
                    ->values()
                    ->all();

                foreach ($contents as $section) {
                    foreach (($section['modules'] ?? []) as $module) {
                        if (($module['modname'] ?? '') !== 'resource') {
                            continue;
                        }

                        $moduleId = (int) ($module['id'] ?? 0);

                        if ($moduleId <= 0) {
                            continue;
                        }

                        if (in_array($moduleId, $accessedCmids, true)) {
                            $statuses = $this->withCompletedActivityStatus(
                                $statuses,
                                $moduleId
                            );

                            $this->rememberLocalActivityCompletion(
                                $courseId,
                                $moduleId
                            );
                        }
                    }
                }
            } catch (\Throwable) {
                // Jika data recently accessed tidak tersedia,
                // gunakan completion Moodle seperti biasa.
            }
            $statuses = $this->withLocalCompletionOverrides($courseId, $statuses);
            $this->completionStore()->rememberCompletedStatuses($currentUserId, $courseId, $statuses);
            $total = is_array($statuses) ? count($statuses) : 0;
            $completed = 0;

            foreach (is_array($statuses) ? $statuses : [] as $status) {
                if ($this->activityCompletionIsDone($status)) {
                    $completed++;
                }
            }

            if ($total > 0) {
                return [
                    'completed' => $completed,
                    'total' => $total,
                    'percent' => (int) round(($completed / $total) * 100),
                    'source' => 'completion',
                    'label' => 'Aktivitas selesai',
                    'statuses' => $statuses,
                ];
            }
        } catch (\Throwable) {
            $statuses = $this->withCourseActivityStatuses($contents, []);
            $statuses = $this->withLocalCompletionOverrides($courseId, $statuses);
            if ($statuses !== []) {
                $progress = $this->gamificationService->summary($statuses);

                return array_merge($progress, [
                    'source' => 'local',
                    'label' => 'Aktivitas selesai',
                    'statuses' => $statuses,
                ]);
            }
        }

        try {
            $gradeSummary = $this->gradeSummariesByUser(
                $this->activeMoodleService()->getUserGrades($courseId, $currentUserId),
            )[$currentUserId] ?? null;

            if (($gradeSummary['total_items'] ?? 0) > 0) {
                $completed = (int) ($gradeSummary['graded_items'] ?? 0);
                $total = (int) ($gradeSummary['total_items'] ?? 0);

                return [
                    'completed' => $completed,
                    'total' => $total,
                    'percent' => (int) round(($completed / max(1, $total)) * 100),
                    'source' => 'grade',
                    'label' => 'Komponen nilai terisi',
                ];
            }
        } catch (\Throwable) {
            // Course tetap ditampilkan walaupun progress tidak tersedia.
        }

        $activityTotal = $this->courseActivityCount($contents);
        if ($activityTotal > 0) {
            return [
                'completed' => 0,
                'total' => $activityTotal,
                'percent' => 0,
                'source' => 'course',
                'label' => 'Aktivitas tersedia',
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $currentProgress
     * @return array{rows: array<int, array<string, mixed>>, current_rank: int|null, total_students: int, complete: bool, message: string|null}
     */
    protected function achievementLeaderboard(int $courseId, array $currentProgress, mixed $contents = null): array
    {
        $sessionUser = session('moodle_user') ?? [];
        $currentUserId = (int) ($sessionUser['id'] ?? 0);
        $students = [];
        $complete = true;
        $message = null;

        try {
            $enrolledUsers = $this->activeMoodleService()->getEnrolledUsers($courseId);
            $students = $this->studentUsers($enrolledUsers);
            if (! is_array($enrolledUsers) || $students === []) {
                $complete = false;
                $message = 'Leaderboard belum dapat dimuat sepenuhnya.';
            }
        } catch (\Throwable) {
            $complete = false;
            $message = 'Leaderboard belum dapat dimuat sepenuhnya.';
        }

        $hasCurrentStudent = collect($students)->contains(fn($student) => (int) ($student['id'] ?? 0) === $currentUserId);
        if ($currentUserId > 0 && ! $hasCurrentStudent) {
            $complete = false;
            $message ??= 'Sebagian data leaderboard belum tersedia.';
            $students[] = [
                'id' => $currentUserId,
                'fullname' => $sessionUser['name'] ?? $sessionUser['fullname'] ?? 'Anda',
                'roles' => [['shortname' => 'student']],
            ];
        }

        $rows = [];
        foreach ($students as $student) {
            $userId = (int) ($student['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if ($userId === $currentUserId) {
                $statuses = is_array($currentProgress['statuses'] ?? null)
                    ? $currentProgress['statuses']
                    : [];
                $statuses = $this->withCourseActivityStatuses($contents, $statuses);
                $statuses = $this->withLocalCompletionOverrides($courseId, $statuses, $userId);
                $progress = $statuses !== []
                    ? $this->gamificationService->summary($statuses)
                    : $currentProgress;
            } else {
                $statuses = [];
                try {
                    $completion = $this->activeMoodleService()->getActivityCompletionStatus($courseId, $userId);
                    $statuses = is_array($completion) && is_array($completion['statuses'] ?? null)
                        ? $completion['statuses']
                        : [];
                } catch (\Throwable) {
                    $complete = false;
                    $message = 'Sebagian data leaderboard belum tersedia.';
                }

                $statuses = $this->withCourseActivityStatuses($contents, $statuses);
                $statuses = $this->withLocalCompletionOverrides($courseId, $statuses, $userId);
                $progress = $this->gamificationService->summary($statuses);
            }

            $this->completionStore()->rememberCompletedStatuses($userId, $courseId, $statuses);

            $rows[] = [
                'id' => $userId,
                'name' => $this->displayUserName($student),
                'is_current' => $userId === $currentUserId,
                'points' => max(0, (int) ($progress['completed'] ?? 0)) * GamificationService::POINTS_PER_ACTIVITY,
            ];
        }

        usort($rows, function (array $left, array $right): int {
            $pointsComparison = ((int) $right['points']) <=> ((int) $left['points']);

            return $pointsComparison !== 0 ? $pointsComparison : strcasecmp((string) $left['name'], (string) $right['name']);
        });

        $lastPoints = null;
        $lastRank = 0;
        foreach ($rows as $index => &$row) {
            $points = (int) $row['points'];
            if ($lastPoints === null || $points !== $lastPoints) {
                $lastRank = $index + 1;
                $lastPoints = $points;
            }
            $row['rank'] = $lastRank;
        }
        unset($row);

        $currentRow = collect($rows)->first(fn($row) => (bool) ($row['is_current'] ?? false));

        return [
            'rows' => $rows,
            'current_rank' => is_array($currentRow) ? (int) ($currentRow['rank'] ?? 0) : null,
            'total_students' => count($students),
            'complete' => $complete,
            'message' => $message,
        ];
    }

    protected function studentUsers(mixed $users): array
    {
        if (! is_array($users)) {
            return [];
        }

        $students = array_values(array_filter($users, function ($user): bool {
            if (! is_array($user)) {
                return false;
            }

            $roles = $user['roles'] ?? [];
            if (! is_array($roles) || $roles === []) {
                return ! $this->isCurrentMoodleUser($user);
            }

            foreach ($roles as $role) {
                $roleName = strtolower((string) ($role['shortname'] ?? $role['name'] ?? ''));
                if (str_contains($roleName, 'student') || str_contains($roleName, 'mahasiswa')) {
                    return true;
                }
            }

            return false;
        }));

        usort($students, fn($a, $b) => strcasecmp($this->displayUserName($a), $this->displayUserName($b)));

        return $students;
    }

    protected function activityCompletionIsDone(mixed $status): bool
    {
        if (! is_array($status)) {
            return false;
        }

        $state = $status['state'] ?? $status['completionstate'] ?? 0;

        return in_array((int) $state, [1, 2, 3], true)
            || (isset($status['completed']) && (bool) $status['completed']);
    }


    protected function gradeSummariesByUser(mixed $gradesData): array
    {
        if (! is_array($gradesData)) {
            return [];
        }

        $summaries = [];

        foreach (($gradesData['usergrades'] ?? []) as $userGrade) {
            $userId = (int) ($userGrade['userid'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $totalItems = 0;
            $gradedItems = 0;
            $numericGrades = [];
            $finalGrade = null;
            $items = [];

            foreach (($userGrade['gradeitems'] ?? []) as $item) {
                if ($this->gradeItemIsHidden($item)) {
                    continue;
                }

                $itemType = strtolower((string) ($item['itemtype'] ?? ''));
                $itemName = trim((string) ($item['itemname'] ?? ''));
                $gradeValue = $this->numericGradeValue($item['grade'] ?? $item['gradeformatted'] ?? null);

                if ($itemType === 'course') {
                    $finalGrade = $gradeValue;
                    continue;
                }

                if ($itemName === '') {
                    continue;
                }

                $totalItems++;
                $items[] = [
                    'id' => $item['id'] ?? $itemName,
                    'name' => $itemName,
                    'grade' => $gradeValue,
                    'raw' => $this->displayGradeValue($item['gradeformatted'] ?? $item['grade'] ?? null),
                ];

                if ($gradeValue !== null) {
                    $gradedItems++;
                    $numericGrades[] = $gradeValue;
                }
            }

            $summaries[$userId] = [
                'final_grade' => $finalGrade,
                'graded_items' => $gradedItems,
                'total_items' => $totalItems,
                'average' => $numericGrades === [] ? null : round(array_sum($numericGrades) / count($numericGrades), 2),
                'items' => $items,
            ];
        }

        return $summaries;
    }

    protected function visibleGradeItems(mixed $gradesData): array
    {
        if (! is_array($gradesData)) {
            return [];
        }

        $items = [];
        foreach (($gradesData['gradeitems'] ?? []) as $item) {
            if ($this->gradeItemIsHidden($item)) {
                continue;
            }

            if (strtolower((string) ($item['itemtype'] ?? '')) === 'course') {
                continue;
            }

            $name = trim((string) ($item['itemname'] ?? ''));
            if ($name === '') {
                continue;
            }

            $items[] = [
                'id' => $item['id'] ?? $name,
                'name' => $name,
            ];
        }

        return $items;
    }

    protected function numericGradeValue(mixed $grade): ?float
    {
        if ($grade === null) {
            return null;
        }

        $text = str_replace(',', '.', trim(strip_tags((string) $grade)));
        if ($text === '' || $text === '-') {
            return null;
        }

        if (preg_match('/-?\d+(?:\.\d+)?/', $text, $match) !== 1) {
            return null;
        }

        return round((float) $match[0], 2);
    }

    protected function displayGradeValue(mixed $grade): ?string
    {
        if ($grade === null) {
            return null;
        }

        $text = trim(strip_tags((string) $grade));

        return $text === '' || $text === '-' ? null : $text;
    }

    protected function courseActivityCount(mixed $contents): int
    {
        if (! is_array($contents)) {
            return 0;
        }

        $count = 0;
        foreach ($contents as $section) {
            $count += count($section['modules'] ?? []);
        }

        return $count;
    }

    protected function displayUserName(array $user): string
    {
        return trim((string) ($user['fullname'] ?? (($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')))) ?: 'Mahasiswa #' . ($user['id'] ?? '-');
    }

    protected function roleLabels(array $user): string
    {
        $roles = [];
        foreach (($user['roles'] ?? []) as $role) {
            $label = trim((string) ($role['shortname'] ?? $role['name'] ?? ''));
            if ($label !== '') {
                $roles[] = $label;
            }
        }

        return $roles === [] ? '-' : implode(', ', array_unique($roles));
    }

    protected function searchDiscoverableCourses(string $query, array $enrolledCourses): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $enrolledIds = collect($enrolledCourses)
            ->pluck('id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->all();
        $enrolledIds = array_flip($enrolledIds);

        $needle = mb_strtolower($query);
        $courses = $this->searchCoursesFromMoodle($query);
        $matches = [];

        foreach ($courses as $course) {
            $courseId = (int) ($course['id'] ?? 0);
            if ($courseId <= 1 || isset($enrolledIds[$courseId])) {
                continue;
            }

            if (! $this->courseMatchesSearch($course, $needle)) {
                continue;
            }

            $matches[] = $course;

            if (count($matches) >= 12) {
                break;
            }
        }

        return $matches;
    }

    protected function searchCoursesFromMoodle(string $query): array
    {
        try {
            $response = $this->activeMoodleService()->searchCourses($query, 0, 50);
            if (is_array($response)) {
                if (isset($response['courses']) && is_array($response['courses'])) {
                    return $response['courses'];
                }

                return $response;
            }
        } catch (\Throwable) {
            // Fall back to the broader course list when Moodle search is not exposed.
        }

        try {
            $courses = $this->activeMoodleService()->getCourses();

            return is_array($courses) ? $courses : [];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function courseMatchesSearch(array $course, string $needle): bool
    {
        foreach (
            [
                $course['fullname'] ?? null,
                $course['displayname'] ?? null,
                $course['shortname'] ?? null,
                $course['idnumber'] ?? null,
                $course['summary'] ?? null,
                $course['id'] ?? null,
            ] as $value
        ) {
            if ($value !== null && str_contains(mb_strtolower((string) $value), $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function flattenCalendarEvents(mixed $calendarResponse): array
    {
        if (! is_array($calendarResponse)) {
            return [];
        }

        $events = [];

        foreach (($calendarResponse['groupedbycourse'] ?? []) as $courseGroup) {
            foreach (($courseGroup['events'] ?? []) as $event) {
                $event['courseid'] ??= $courseGroup['courseid'] ?? null;
                $events[] = $event;
            }
        }

        if ($events === [] && isset($calendarResponse['events']) && is_array($calendarResponse['events'])) {
            $events = $calendarResponse['events'];
        }

        $events = array_map(function ($event): array {
            $event = is_array($event) ? $event : [];
            $moduleName = strtolower((string) ($event['modulename'] ?? ''));
            if (in_array($moduleName, ['assign', 'quiz'], true)) {
                $activityName = $this->notificationActivityDisplayName($event);
                if ($activityName !== '') {
                    $event['name'] = $activityName;
                }
            }

            return $event;
        }, $events);

        usort($events, fn($a, $b) => (int) ($a['timesort'] ?? $a['timestart'] ?? 0) <=> (int) ($b['timesort'] ?? $b['timestart'] ?? 0));

        return $events;
    }

    protected function notificationEventsForCourses(mixed $courses): array
    {
        $courses = is_array($courses) ? $courses : [];
        $courseIds = collect($courses)
            ->pluck('id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        if ($courseIds === []) {
            return [];
        }

        try {
            $events = $this->flattenCalendarEvents(
                $this->activeMoodleService()->getCalendarActionEventsByCourses($courseIds),
            );
        } catch (\Throwable) {
            $events = [];
        }

        array_push($events, ...$this->fallbackDeadlineEventsForCourses($courses));
        array_push($events, ...$this->assignmentGradeNotificationEventsForCourses($courses));

        return $this->deduplicateNotificationEvents($events);
    }

    protected function cachedNotificationCoursesForCurrentUser(): array
    {
        return Cache::remember(
            $this->notificationCoursesCacheKey(),
            now()->addSeconds(30),
            fn (): array => $this->moodleCoursesForUsername(session('username')),
        );
    }

    protected function cachedNotificationEventsForCourses(mixed $courses): array
    {
        $courses = is_array($courses) ? $courses : [];
        $courseIds = collect($courses)
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($courseIds === []) {
            return [];
        }

        return Cache::remember(
            $this->notificationEventsCacheKey($courseIds),
            now()->addSeconds(30),
            fn (): array => $this->notificationEventsForCourses($courses),
        );
    }

    protected function notificationCoursesCacheKey(): string
    {
        return 'notification_courses:v1:' . $this->notificationUserCacheIdentity();
    }

    /**
     * @param  array<int, int>  $courseIds
     */
    protected function notificationEventsCacheKey(array $courseIds): string
    {
        return 'notification_events:v1:' . $this->notificationUserCacheIdentity() . ':' . sha1(implode(',', $courseIds));
    }

    protected function notificationUserCacheIdentity(): string
    {
        $userId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        if ($userId > 0) {
            return (string) $userId;
        }

        return sha1((string) session('username', 'guest'));
    }

    protected function assignmentGradeNotificationEventsForCourses(array $courses): array
    {
        $userId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $events = [];

        foreach ($courses as $course) {
            if (! is_array($course)) {
                continue;
            }

            $courseId = (int) ($course['id'] ?? 0);
            if ($courseId <= 0) {
                continue;
            }

            try {
                $gradesData = $this->activeMoodleService()->getUserGrades($courseId, $userId);
            } catch (\Throwable) {
                continue;
            }

            $gradeItems = is_array($gradesData['gradeitems'] ?? null)
                ? $gradesData['gradeitems']
                : [];

            foreach ($gradeItems as $item) {
                if (! is_array($item)
                    || strtolower((string) ($item['itemmodule'] ?? '')) !== 'assign'
                    || $this->gradeItemIsHidden($item)) {
                    continue;
                }

                $grade = $this->firstFilledString(
                    $item['gradefordisplay'] ?? null,
                    $item['gradeformatted'] ?? null,
                    $item['str_grade'] ?? null,
                    $item['graderaw'] ?? null,
                    $item['grade'] ?? null,
                );
                $gradedAt = (int) ($item['gradedategraded'] ?? 0);

                if (! $this->hasVisibleGrade($grade) || $gradedAt <= 0) {
                    continue;
                }

                $assignmentName = trim(strip_tags((string) ($item['itemname'] ?? 'Tugas')));
                $events[] = $this->moodleNotificationEvent([
                    'name' => $assignmentName !== '' ? $assignmentName : 'Tugas',
                    'courseid' => $courseId,
                    'course_name' => $course['fullname'] ?? $course['displayname'] ?? 'Kursus',
                    'cmid' => $item['cmid'] ?? null,
                    'instance' => $item['iteminstance'] ?? null,
                    'modulename' => 'assign',
                    'eventtype' => 'assignment_graded',
                    'time' => $gradedAt,
                    'source' => 'assignment-grade',
                    'action' => 'view-grade',
                ]);
            }
        }

        return $events;
    }

    protected function fallbackDeadlineEventsForCourses(array $courses): array
    {
        $events = [];
        $courseNames = [];

        foreach ($courses as $course) {
            if (! is_array($course)) {
                continue;
            }

            $courseId = (int) ($course['id'] ?? 0);
            if ($courseId <= 0) {
                continue;
            }

            $courseNames[$courseId] = $course['fullname'] ?? $course['displayname'] ?? 'Kursus';

            try {
                $assignmentsResponse = $this->activeMoodleService()->getAssignments($courseId);
                foreach ((is_array($assignmentsResponse) ? ($assignmentsResponse['courses'] ?? []) : []) as $assignmentCourse) {
                    foreach (($assignmentCourse['assignments'] ?? []) as $assignment) {
                        $due = (int) ($assignment['duedate'] ?? 0);
                        if ($due > 0) {
                            $events[] = $this->moodleDeadlineEvent([
                                'name' => $assignment['name'] ?? 'Tugas',
                                'courseid' => $courseId,
                                'course_name' => $courseNames[$courseId],
                                'cmid' => $assignment['cmid'] ?? null,
                                'instance' => $assignment['id'] ?? null,
                                'modulename' => 'assign',
                                'eventtype' => 'deadline',
                                'time' => $due,
                                'source' => 'assignment',
                            ]);
                        }
                    }
                }
            } catch (\Throwable) {
                //
            }

            try {
                $quizzesResponse = $this->activeMoodleService()->getQuizzes($courseId);
                foreach ((is_array($quizzesResponse) ? ($quizzesResponse['quizzes'] ?? []) : []) as $quiz) {
                    $close = (int) ($quiz['timeclose'] ?? 0);
                    if ($close > 0) {
                        $events[] = $this->moodleDeadlineEvent([
                            'name' => $quiz['name'] ?? 'Kuis',
                            'courseid' => $courseId,
                            'course_name' => $courseNames[$courseId],
                            'cmid' => $quiz['coursemodule'] ?? null,
                            'instance' => $quiz['id'] ?? null,
                            'modulename' => 'quiz',
                            'eventtype' => 'deadline',
                            'time' => $close,
                            'source' => 'quiz',
                        ]);
                    }
                }
            } catch (\Throwable) {
                //
            }

            try {
                $courseContents = $this->activeMoodleService()->getCourseContents($courseId);
                foreach ((is_array($courseContents) ? $courseContents : []) as $section) {
                    foreach (($section['modules'] ?? []) as $module) {
                        $activityEvent = $this->moduleActivityEvent($module, $section, $courseId, $courseNames[$courseId]);
                        if ($activityEvent !== null) {
                            $events[] = $activityEvent;
                        }
                        array_push($events, ...$this->moduleDateEvents($module, $section, $courseId, $courseNames[$courseId]));
                    }
                }
            } catch (\Throwable) {
                //
            }
        }

        return array_values(array_filter($events, fn($event): bool => $this->isRelevantNotificationEvent($event)));
    }

    protected function moduleActivityEvent(array $module, array $section, int $courseId, string $courseName): ?array
    {
        $moduleName = strtolower((string) ($module['modname'] ?? ''));
        $addedAt = (int) ($module['added'] ?? 0);

        if ($moduleName === '') {
            return null;
        }

        $eventType = match ($moduleName) {
            'assign' => 'tugas',
            'quiz' => 'kuis',
            'url', 'resource', 'page', 'book', 'folder', 'lesson', 'scorm', 'wiki', 'glossary' => 'materi',
            default => 'aktivitas',
        };

        return $this->moodleNotificationEvent([
            'name' => $module['name'] ?? 'Aktivitas Pembelajaran',
            'courseid' => $courseId,
            'course_name' => $courseName,
            'cmid' => $module['id'] ?? null,
            'instance' => $module['instance'] ?? null,
            'modulename' => $moduleName,
            'eventtype' => $eventType,
            'time' => $addedAt,
            'source' => 'course-content',
            'section' => $section['name'] ?? $section['summary'] ?? null,
        ]);
    }

    protected function moduleDateEvents(array $module, array $section, int $courseId, string $courseName): array
    {
        $events = [];
        $moduleName = strtolower((string) ($module['modname'] ?? ''));
        $dates = is_array($module['dates'] ?? null) ? $module['dates'] : [];

        foreach ($dates as $date) {
            $timestamp = (int) ($date['timestamp'] ?? 0);
            if ($timestamp <= 0) {
                continue;
            }

            $label = trim(strip_tags((string) ($date['label'] ?? $date['name'] ?? 'Jadwal')));
            $isDeadline = str_contains(mb_strtolower($label), 'due')
                || str_contains(mb_strtolower($label), 'close')
                || str_contains(mb_strtolower($label), 'akhir')
                || str_contains(mb_strtolower($label), 'deadline')
                || str_contains(mb_strtolower($label), 'batas');

            $events[] = $this->moodleNotificationEvent([
                'name' => ($module['name'] ?? 'Aktivitas Pembelajaran') . ($label !== '' ? ' - ' . $label : ''),
                'courseid' => $courseId,
                'course_name' => $courseName,
                'cmid' => $module['id'] ?? null,
                'instance' => $module['instance'] ?? null,
                'modulename' => $moduleName !== '' ? $moduleName : 'activity',
                'eventtype' => $isDeadline ? 'deadline' : 'jadwal',
                'time' => $timestamp,
                'source' => 'course-content',
                'section' => $section['name'] ?? $section['summary'] ?? null,
            ]);
        }

        return $events;
    }

    protected function moodleNotificationEvent(array $event): array
    {
        $time = (int) ($event['time'] ?? 0);

        return [
            'id' => 'lite-' . $this->notificationEventKey([
                'id' => $event['id'] ?? null,
                'courseid' => $event['courseid'] ?? null,
                'instance' => $event['instance'] ?? null,
                'modulename' => $event['modulename'] ?? null,
                'eventtype' => $event['eventtype'] ?? null,
                'name' => $event['name'] ?? null,
                'timesort' => $time,
            ]),
            'name' => $event['name'] ?? 'Aktivitas Pembelajaran',
            'courseid' => $event['courseid'] ?? null,
            'cmid' => $event['cmid'] ?? null,
            'instance' => $event['instance'] ?? null,
            'modulename' => $event['modulename'] ?? 'moodle',
            'eventtype' => $event['eventtype'] ?? 'deadline',
            'timesort' => $time,
            'timestart' => $time,
            'source' => $event['source'] ?? 'lite',
            'description' => $event['description'] ?? null,
            'action' => $event['action'] ?? null,
            'course' => [
                'id' => $event['courseid'] ?? null,
                'fullname' => $event['course_name'] ?? 'Kursus',
            ],
        ];
    }

    protected function moodleDeadlineEvent(array $event): array
    {
        return $this->moodleNotificationEvent($event);
    }

    protected function deduplicateNotificationEvents(array $events): array
    {
        $unique = [];
        $moduleIdsByInstance = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $courseId = (int) ($event['courseid'] ?? ($event['course']['id'] ?? 0));
            $moduleName = strtolower((string) ($event['modulename'] ?? ''));
            $moduleId = (int) ($event['cmid'] ?? 0);
            $instanceId = (int) ($event['instance'] ?? 0);

            if ($courseId > 0 && $moduleName !== '' && $moduleId > 0 && $instanceId > 0) {
                $moduleIdsByInstance[$courseId . ':' . $moduleName . ':' . $instanceId] = $moduleId;
            }
        }

        foreach ($events as $event) {
            if (! is_array($event) || ! $this->isRelevantNotificationEvent($event)) {
                continue;
            }

            if ((int) ($event['cmid'] ?? 0) <= 0 && (int) ($event['instance'] ?? 0) > 0) {
                $courseId = (int) ($event['courseid'] ?? ($event['course']['id'] ?? 0));
                $moduleName = strtolower((string) ($event['modulename'] ?? ''));
                $instanceKey = $courseId . ':' . $moduleName . ':' . (int) $event['instance'];
                if (isset($moduleIdsByInstance[$instanceKey])) {
                    $event['cmid'] = $moduleIdsByInstance[$instanceKey];
                }
            }

            $activityKey = $this->notificationActivityKey($event);
            $eventKey = $activityKey ?? $this->notificationEventKey($event);

            if (! isset($unique[$eventKey])) {
                $unique[$eventKey] = $event;
                continue;
            }

            $current = $unique[$eventKey];
            $eventPriority = $this->notificationEventPriority($event);
            $currentPriority = $this->notificationEventPriority($current);

            if ($eventPriority > $currentPriority
                || ($eventPriority === $currentPriority
                    && $this->notificationEventTime($event) > $this->notificationEventTime($current))) {
                $unique[$eventKey] = $event;
            }
        }

        $events = array_values($unique);
        usort($events, fn($a, $b) => $this->notificationEventTime($a) <=> $this->notificationEventTime($b));

        return $events;
    }

    protected function notificationActivityKey(array $event): ?string
    {
        $moduleName = strtolower((string) ($event['modulename'] ?? ''));
        if (! in_array($moduleName, ['assign', 'quiz'], true)) {
            return null;
        }

        $courseId = (int) ($event['courseid'] ?? ($event['course']['id'] ?? 0));
        $activityName = mb_strtolower($this->notificationActivityDisplayName($event));
        if ($courseId > 0 && $activityName !== '') {
            return 'activity:' . $courseId . ':' . $moduleName . ':name-' . sha1($activityName);
        }

        $moduleId = (int) ($event['cmid'] ?? 0);
        $instanceId = (int) ($event['instance'] ?? 0);
        $activityId = $moduleId > 0 ? 'cm-' . $moduleId : ($instanceId > 0 ? 'instance-' . $instanceId : null);

        return $courseId > 0 && $activityId !== null
            ? 'activity:' . $courseId . ':' . $moduleName . ':' . $activityId
            : null;
    }

    protected function notificationActivityDisplayName(array $event): string
    {
        $name = $this->moodlePlainText($event['activityname'] ?? $event['name'] ?? '');
        $name = preg_replace(
            '/\s*(?:[-–—:]\s*)?(?:jatuh\s+tempo|dibuka|buka|mulai|akan\s+berakhir|akan\s+ditutup|ditutup|tutup|penutupan|is\s+due|closes?|opens?|due|close|open)\s*:?[.!]?\s*$/iu',
            '',
            $name,
        ) ?? $name;

        return trim($name);
    }

    protected function notificationEventPriority(array $event): int
    {
        $source = strtolower((string) ($event['source'] ?? ''));
        $eventType = strtolower((string) ($event['eventtype'] ?? ''));

        if ($eventType === 'assignment_graded' || $source === 'assignment-grade') {
            return 500;
        }

        if (in_array($source, ['assignment', 'quiz'], true)) {
            return 400;
        }

        if ($this->isDeadlineNotificationEvent($event)) {
            return 300;
        }

        return $this->notificationEventTime($event) > 0 ? 200 : 100;
    }

    protected function upcomingDeadlineEvents(array $events, int $limit = 5): array
    {
        $now = time();

        $events = array_values(array_filter($events, function ($event) use ($now): bool {
            return is_array($event) && $this->notificationEventTime($event) >= $now;
        }));

        usort($events, fn($a, $b) => $this->notificationEventTime($a) <=> $this->notificationEventTime($b));

        return array_slice($events, 0, $limit);
    }

    protected function deadlineEventsByCourse(array $events, ?int $limit = null): array
    {
        $grouped = [];

        foreach ($this->deadlineNotificationEvents($events) as $event) {
            $time = $this->notificationEventTime($event);
            if ($time < time()) {
                continue;
            }

            $courseId = (int) ($event['courseid'] ?? ($event['course']['id'] ?? 0));
            if ($courseId <= 0) {
                continue;
            }

            $grouped[$courseId][] = $event;
        }

        foreach ($grouped as $courseId => $courseEvents) {
            usort($courseEvents, fn($a, $b) => $this->notificationEventTime($a) <=> $this->notificationEventTime($b));
            $grouped[$courseId] = $limit === null ? $courseEvents : array_slice($courseEvents, 0, $limit);
        }

        return $grouped;
    }

    protected function withoutCompletedDeadlineEvents(array $events, array $courses): array
    {
        $completedModulesByCourse = [];

        foreach ($courses as $course) {
            if (! is_array($course)) {
                continue;
            }

            $courseId = (int) ($course['id'] ?? 0);
            $statuses = $course['progress']['statuses'] ?? [];
            if ($courseId <= 0 || ! is_array($statuses)) {
                continue;
            }

            foreach ($statuses as $status) {
                if (! $this->activityCompletionIsDone($status)) {
                    continue;
                }

                $moduleId = (int) ($status['cmid'] ?? $status['coursemoduleid'] ?? 0);
                if ($moduleId > 0) {
                    $completedModulesByCourse[$courseId][$moduleId] = true;
                }
            }
        }

        return array_values(array_filter($events, function ($event) use ($completedModulesByCourse): bool {
            if (! is_array($event) || ! $this->isDeadlineNotificationEvent($event)) {
                return true;
            }

            $courseId = (int) ($event['courseid'] ?? ($event['course']['id'] ?? 0));
            $moduleId = (int) ($event['cmid'] ?? 0);

            return $courseId <= 0
                || $moduleId <= 0
                || ! isset($completedModulesByCourse[$courseId][$moduleId]);
        }));
    }

    protected function deadlineNotificationEvents(array $events): array
    {
        $events = array_values(array_filter($events, function ($event): bool {
            return is_array($event) && $this->isDeadlineNotificationEvent($event);
        }));

        usort($events, fn($a, $b) => $this->notificationEventTime($a) <=> $this->notificationEventTime($b));

        return $events;
    }

    protected function taskNotificationEvents(array $events): array
    {
        return array_values(array_filter(
            $events,
            fn($event): bool => is_array($event)
                && strtolower((string) ($event['eventtype'] ?? '')) === 'assignment_graded',
        ));
    }

    protected function isDeadlineNotificationEvent(array $event): bool
    {
        $eventType = strtolower((string) ($event['eventtype'] ?? ''));

        return in_array($eventType, ['deadline', 'due', 'close', 'closing', 'assign', 'assignment', 'quiz'], true);
    }

    protected function isRelevantNotificationEvent(array $event): bool
    {
        $time = $this->notificationEventTime($event);
        if (($event['source'] ?? null) === 'assignment-grade') {
            return $time > 0 && $time >= strtotime('-30 days');
        }

        if ($time <= 0) {
            return ($event['source'] ?? null) === 'course-content'
                && in_array(strtolower((string) ($event['eventtype'] ?? '')), ['materi', 'tugas', 'kuis', 'aktivitas'], true);
        }

        return $time >= strtotime('-1 day');
    }

    protected function notificationEventTime(array $event): int
    {
        return (int) ($event['timesort'] ?? $event['timestart'] ?? $event['time'] ?? 0);
    }

    protected function unreadNotificationCount(array $events): int
    {
        return count($this->unreadNotificationKeys($events));
    }

    /**
     * @param  array<int, string>|null  $unreadKeys
     * @return array{all: int, deadline: int, task: int}
     */
    protected function unreadNotificationCounts(array $events, ?array $unreadKeys = null): array
    {
        $unreadKeys ??= $this->unreadNotificationKeys($events);
        $unreadLookup = array_flip($unreadKeys);
        $countUnreadEvents = function (array $categoryEvents) use ($unreadLookup): int {
            $count = 0;

            foreach ($categoryEvents as $event) {
                if (is_array($event) && isset($unreadLookup[$this->notificationEventKey($event)])) {
                    $count++;
                }
            }

            return $count;
        };

        return [
            'all' => count($unreadKeys),
            'deadline' => $countUnreadEvents($this->deadlineNotificationEvents($events)),
            'task' => $countUnreadEvents($this->taskNotificationEvents($events)),
        ];
    }

    protected function unreadNotificationKeys(array $events): array
    {
        $sessionReadKeys = session($this->notificationReadSessionKey(), []);
        $sessionReadKeys = is_array($sessionReadKeys) ? $sessionReadKeys : [];
        $sessionReadLookup = array_flip($sessionReadKeys);
        $eventKeys = [];

        foreach ($events as $event) {
            if (is_array($event)) {
                $eventKeys[] = $this->notificationEventKey($event);
            }
        }

        $userId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        $persistedReadKeys = $this->notificationReadStore()->readKeysFor($userId, $eventKeys);
        $persistedReadLookup = array_flip($persistedReadKeys);
        $unreadKeys = [];
        $keysToMigrate = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $key = $this->notificationEventKey($event);
            $legacyKey = $this->legacyNotificationEventKey($event);
            $isRead = isset($persistedReadLookup[$key])
                || isset($sessionReadLookup[$key])
                || isset($sessionReadLookup[$legacyKey]);

            if (! $isRead) {
                $unreadKeys[] = $key;
            } elseif (! isset($persistedReadLookup[$key])) {
                $keysToMigrate[] = $key;
            }
        }

        $this->notificationReadStore()->rememberMany($userId, $keysToMigrate);

        return array_values(array_unique($unreadKeys));
    }

    protected function markNotificationsAsRead(array $events): void
    {
        $readKeys = session($this->notificationReadSessionKey(), []);
        if (! is_array($readKeys)) {
            $readKeys = [];
        }

        $eventKeys = array_values(array_unique(array_map(
            fn (array $event): string => $this->notificationEventKey($event),
            array_values(array_filter($events, 'is_array')),
        )));

        array_push($readKeys, ...$eventKeys);

        $userId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        $this->notificationReadStore()->rememberMany($userId, $eventKeys);

        session([$this->notificationReadSessionKey() => array_slice(array_values(array_unique($readKeys)), -250)]);
    }

    protected function notificationEventKey(array $event): string
    {
        return sha1(json_encode([
            'courseid' => (int) ($event['courseid'] ?? ($event['course']['id'] ?? 0)),
            'activity' => $this->notificationActivityKey($event)
                ?? mb_strtolower($this->notificationActivityDisplayName($event)),
            'kind' => strtolower((string) ($event['eventtype'] ?? '')) === 'assignment_graded'
                ? 'assignment_graded'
                : ($this->isDeadlineNotificationEvent($event)
                    ? 'deadline'
                    : strtolower((string) ($event['eventtype'] ?? 'aktivitas'))),
            'time' => $event['timesort'] ?? $event['timestart'] ?? null,
        ]));
    }

    protected function legacyNotificationEventKey(array $event): string
    {
        return sha1(json_encode([
            'id' => $event['id'] ?? null,
            'courseid' => $event['courseid'] ?? null,
            'cmid' => $event['cmid'] ?? null,
            'instance' => $event['instance'] ?? null,
            'modulename' => $event['modulename'] ?? null,
            'eventtype' => $event['eventtype'] ?? null,
            'name' => $event['name'] ?? null,
            'time' => $event['timesort'] ?? $event['timestart'] ?? null,
        ]));
    }

    protected function notificationReadSessionKey(): string
    {
        $userId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);

        return 'read_notification_events.' . ($userId > 0 ? $userId : session('username', 'guest'));
    }

    protected function notificationReadStore(): NotificationReadStore
    {
        return $this->notificationReadStore ??= app(NotificationReadStore::class);
    }

    protected function activityCompletionOverrideKey(int $courseId, int $moduleId): string
    {
        $userId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);

        return 'activity_completion_overrides.' . $userId . '.' . $courseId . '.' . $moduleId;
    }

    protected function localActivityCompletionCacheKey(int $courseId, ?int $userId = null): string
    {
        $userId ??= (int) ((session('moodle_user') ?? [])['id'] ?? 0);

        return 'local_activity_completions:v1:' . $userId . ':' . $courseId;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function localActivityCompletionOverrides(int $courseId, ?int $userId = null): array
    {
        $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        $userId ??= $currentUserId;
        if ($userId <= 0 || $courseId <= 0) {
            return [];
        }

        $persisted = $this->completionStore()->completionsFor($userId, $courseId);
        $cached = Cache::get($this->localActivityCompletionCacheKey($courseId, $userId), []);
        $sessionOverrides = $userId === $currentUserId
            ? session('activity_completion_overrides.' . $userId . '.' . $courseId, [])
            : [];
        $legacyOverrides = array_replace(
            is_array($cached) ? $cached : [],
            is_array($sessionOverrides) ? $sessionOverrides : [],
        );

        foreach ($legacyOverrides as $moduleId => $completedAt) {
            if ($completedAt) {
                $this->completionStore()->remember(
                    $userId,
                    $courseId,
                    (int) $moduleId,
                    is_numeric($completedAt) ? (int) $completedAt : null,
                );
            }
        }

        return array_replace(
            $legacyOverrides,
            $persisted,
        );
    }

    protected function rememberLocalActivityCompletion(int $courseId, int $moduleId): void
    {
        $userId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        if ($userId <= 0 || $courseId <= 0 || $moduleId <= 0) {
            return;
        }

        $completedAt = time();
        $overrides = $this->localActivityCompletionOverrides($courseId);
        $overrides[$moduleId] = is_numeric($overrides[$moduleId] ?? null)
            ? (int) $overrides[$moduleId]
            : $completedAt;

        $this->completionStore()->remember($userId, $courseId, $moduleId, $overrides[$moduleId]);
        Cache::forever($this->localActivityCompletionCacheKey($courseId, $userId), $overrides);
        session()->put($this->activityCompletionOverrideKey($courseId, $moduleId), $overrides[$moduleId]);
    }

    /**
     * @param  array<int, mixed>  $statuses
     * @return array<int, mixed>
     */
    protected function withLocalCompletionOverrides(
        int $courseId,
        array $statuses,
        ?int $userId = null
    ): array
    {
        $overrides = $this->localActivityCompletionOverrides($courseId, $userId);
        if (! is_array($overrides) || $overrides === []) {
            return $statuses;
        }

        foreach ($overrides as $moduleId => $completedAt) {
            if (! $completedAt) {
                continue;
            }

            $statuses = $this->withCompletedActivityStatus(
                $statuses,
                (int) $moduleId,
                is_numeric($completedAt) ? (int) $completedAt : null,
            );
        }

        return $statuses;
    }

    protected function completionStore(): GamificationCompletionStore
    {
        return $this->gamificationCompletionStore ??= app(GamificationCompletionStore::class);
    }

    /**
     * @param  array<int, mixed>  $statuses
     * @return array<int, mixed>
     */
    protected function withCourseActivityStatuses(mixed $contents, array $statuses): array
    {
        if (! is_array($contents)) {
            return array_values($statuses);
        }

        $knownModuleIds = [];
        foreach ($statuses as $status) {
            if (! is_array($status)) {
                continue;
            }

            $statusModuleId = (int) ($status['cmid'] ?? $status['coursemoduleid'] ?? 0);
            if ($statusModuleId > 0) {
                $knownModuleIds[$statusModuleId] = true;
            }
        }

        $trackableTypes = ['resource', 'page', 'book', 'folder', 'url', 'assign', 'quiz'];
        foreach ($contents as $section) {
            foreach (($section['modules'] ?? []) as $module) {
                $moduleId = (int) ($module['id'] ?? 0);
                $moduleType = strtolower((string) ($module['modname'] ?? ''));
                if ($moduleId <= 0 || ! in_array($moduleType, $trackableTypes, true) || isset($knownModuleIds[$moduleId])) {
                    continue;
                }

                $statuses[] = ['cmid' => $moduleId, 'state' => 0, 'completed' => false];
                $knownModuleIds[$moduleId] = true;
            }
        }

        return array_values($statuses);
    }

    protected function findModuleInCourseContents(mixed $contents, int $moduleId): array
    {
        if (! is_array($contents)) {
            return [null, null];
        }

        foreach ($contents as $section) {
            foreach (($section['modules'] ?? []) as $module) {
                if ((int) ($module['id'] ?? 0) === $moduleId) {
                    return [$module, $section];
                }
            }
        }

        return [null, null];
    }

    protected function findAssignmentForModule(mixed $assignmentsResponse, array $module): ?array
    {
        if (! is_array($assignmentsResponse)) {
            return null;
        }

        $moduleId = (int) ($module['id'] ?? 0);
        $instanceId = (int) ($module['instance'] ?? 0);

        foreach (($assignmentsResponse['courses'] ?? []) as $course) {
            foreach (($course['assignments'] ?? []) as $assignment) {
                if ((int) ($assignment['cmid'] ?? 0) === $moduleId || (int) ($assignment['id'] ?? 0) === $instanceId) {
                    return $assignment;
                }
            }
        }

        return null;
    }

    protected function assignmentAttachments(?array $assignment, array $module): array
    {
        $sources = [
            $assignment['activityattachments'] ?? [],
            $assignment['introattachments'] ?? [],
            $module['contents'] ?? [],
        ];
        $attachments = [];
        $seen = [];

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            foreach ($source as $file) {
                if (! is_array($file)) {
                    continue;
                }

                $filename = trim((string) ($file['filename'] ?? ''));
                $fileUrl = trim((string) ($file['fileurl'] ?? ''));

                if ($filename === '' || $filename === '.' || $fileUrl === '') {
                    continue;
                }

                $key = strtolower($filename).'|'.$fileUrl;
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $attachments[] = array_merge($file, [
                    'filename' => $filename,
                    'fileurl' => $fileUrl,
                ]);
            }
        }

        return $attachments;
    }

    protected function findResourceForModule(mixed $resourcesResponse, array $module): ?array
    {
        if (! is_array($resourcesResponse)) {
            return null;
        }

        $moduleId = (int) ($module['id'] ?? 0);
        $instanceId = (int) ($module['instance'] ?? 0);

        foreach (($resourcesResponse['resources'] ?? []) as $resource) {
            if (! is_array($resource)) {
                continue;
            }

            $resourceModuleId = (int) ($resource['coursemodule'] ?? $resource['cmid'] ?? 0);
            if ($resourceModuleId === $moduleId || (int) ($resource['id'] ?? 0) === $instanceId) {
                return $resource;
            }
        }

        return null;
    }

    protected function findQuizForModule(mixed $quizzesResponse, array $module): ?array
    {
        if (! is_array($quizzesResponse)) {
            return null;
        }

        $moduleId = (int) ($module['id'] ?? 0);
        $instanceId = (int) ($module['instance'] ?? 0);

        foreach (($quizzesResponse['quizzes'] ?? []) as $quiz) {
            if ((int) ($quiz['coursemodule'] ?? 0) === $moduleId || (int) ($quiz['id'] ?? 0) === $instanceId) {
                return $quiz;
            }
        }

        return null;
    }

    protected function resolveQuizModule(int $courseId, int $moduleId): array
    {
        if ($courseId <= 0 || $moduleId <= 0) {
            throw new \InvalidArgumentException('ID kursus atau kuis tidak valid.');
        }

        $courseContents = $this->activeMoodleService()->getCourseContents($courseId);
        [$module, $section] = $this->findModuleInCourseContents($courseContents, $moduleId);

        if (! $module || ($module['modname'] ?? null) !== 'quiz') {
            throw new \InvalidArgumentException('Module ini bukan kuis Moodle.');
        }

        $quiz = $this->findQuizForModule(
            $this->activeMoodleService()->getQuizzes($courseId),
            $module,
        );

        if (empty($quiz['id'])) {
            throw new \RuntimeException('Detail kuis tidak ditemukan dari Moodle.');
        }

        return [$module, $section, $quiz];
    }

    protected function quizAttemptDeadline(array $quiz, array $attempt = []): ?int
    {
        $deadlines = [];
        $timeClose = (int) ($quiz['timeclose'] ?? 0);
        $timeLimit = (int) ($quiz['timelimit'] ?? 0);
        $timeStart = (int) ($attempt['timestart'] ?? 0);
        $timeCheckState = (int) ($attempt['timecheckstate'] ?? 0);

        if ($timeClose > 0) {
            $deadlines[] = $timeClose;
        }
        if ($timeLimit > 0 && $timeStart > 0) {
            $deadlines[] = $timeStart + $timeLimit;
        }
        if ($timeCheckState > 0) {
            $deadlines[] = $timeCheckState;
        }

        return $deadlines !== [] ? min($deadlines) : null;
    }

    /**
     * @return array{score: float|null, max_score: float}
     */
    protected function quizReviewScore(array $quiz, array $review): array
    {
        $maxScore = (float) ($quiz['grade'] ?? 100);
        $maxScore = $maxScore > 0 ? $maxScore : 100.0;
        $score = isset($review['grade']) && is_numeric($review['grade'])
            ? (float) $review['grade']
            : null;

        if ($score === null) {
            $rawScore = $review['attempt']['sumgrades'] ?? $review['sumgrades'] ?? null;
            $rawMaximum = $quiz['sumgrades'] ?? null;
            if (is_numeric($rawScore) && is_numeric($rawMaximum) && (float) $rawMaximum > 0) {
                $score = ((float) $rawScore / (float) $rawMaximum) * $maxScore;
            }
        }

        return [
            'score' => $score !== null ? round($score, 2) : null,
            'max_score' => round($maxScore, 2),
        ];
    }

    protected function currentQuizAttempt(int $quizId, int $userId): ?array
    {
        $attempts = $this->activeMoodleService()->getQuizUserAttempts($quizId, $userId, 'unfinished');
        if (! is_array($attempts)) {
            return null;
        }

        foreach (($attempts['attempts'] ?? []) as $attempt) {
            if (in_array(($attempt['state'] ?? null), ['inprogress', 'overdue'], true)) {
                return $attempt;
            }
        }

        return null;
    }

    protected function assignmentSubmissionsForGrading(mixed $submissionsResponse, mixed $enrolledUsers, mixed $gradesResponse = null): array
    {
        if (! is_array($submissionsResponse)) {
            return [];
        }

        $usersById = [];
        if (is_array($enrolledUsers)) {
            foreach ($enrolledUsers as $user) {
                $userId = (int) ($user['id'] ?? 0);
                if ($userId > 0) {
                    $usersById[$userId] = $user;
                }
            }
        }

        $gradesByUser = $this->assignmentGradesByUser($gradesResponse);

        $items = [];
        foreach (($submissionsResponse['assignments'] ?? []) as $assignment) {
            foreach (($assignment['submissions'] ?? []) as $submission) {
                $userId = (int) ($submission['userid'] ?? 0);
                $user = $usersById[$userId] ?? [];
                $gradeData = $gradesByUser[$userId] ?? [];

                $items[] = [
                    'userid' => $userId,
                    'student_name' => $user['fullname'] ?? $user['name'] ?? 'Mahasiswa #' . $userId,
                    'student_email' => $user['email'] ?? null,
                    'status' => $submission['status'] ?? '-',
                    'grading_status' => $gradeData['workflowstate'] ?? $submission['gradingstatus'] ?? $submission['workflowstate'] ?? null,
                    'grade' => $gradeData['grade'] ?? null,
                    'feedback' => $gradeData['feedback'] ?? null,
                    'timemodified' => $submission['timemodified'] ?? null,
                    'attemptnumber' => $submission['attemptnumber'] ?? null,
                    'text' => $this->submissionTextFromPlugins($submission['plugins'] ?? []),
                    'files' => $this->submissionFilesFromPlugins($submission['plugins'] ?? []),
                ];
            }
        }

        usort($items, fn($a, $b) => strcasecmp($a['student_name'], $b['student_name']));

        return $items;
    }

    protected function currentUserAssignmentGrade(int $courseId, array $assignment, mixed $submissionStatus = null): ?array
    {
        $currentUserId = (int) ((session('moodle_user') ?? [])['id'] ?? 0);
        if ($currentUserId <= 0) {
            return null;
        }

        try {
            $gradesData = $this->activeMoodleService()->getUserGrades($courseId, $currentUserId);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($gradesData)) {
            return null;
        }

        $assignmentId = (int) ($assignment['id'] ?? 0);
        $cmid = (int) ($assignment['cmid'] ?? 0);
        $assignmentGradeData = [];
        $submissionFeedback = $this->assignmentFeedbackFromSubmissionStatus($submissionStatus);

        if ($assignmentId > 0) {
            try {
                $assignmentGradeData = $this->assignmentGradesByUser(
                    $this->activeMoodleService()->getAssignmentGrades($assignmentId),
                )[$currentUserId] ?? [];
            } catch (\Throwable) {
                $assignmentGradeData = [];
            }
        }

        foreach (($gradesData['usergrades'] ?? []) as $userGrade) {
            if ((int) ($userGrade['userid'] ?? 0) !== $currentUserId) {
                continue;
            }

            foreach (($userGrade['gradeitems'] ?? []) as $item) {
                if (! $this->gradeItemMatchesAssignment($item, $assignmentId, $cmid)) {
                    continue;
                }

                $grade = $this->firstFilledString(
                    $item['gradeformatted'] ?? null,
                    $item['grade'] ?? null,
                    $assignmentGradeData['gradeformatted'] ?? null,
                    $assignmentGradeData['grade'] ?? null,
                    $submissionFeedback['gradeformatted'] ?? null,
                    $submissionFeedback['grade'] ?? null,
                );
                $hasVisibleGrade = $this->hasVisibleGrade($grade);

                if ($this->gradeItemIsHidden($item) && ! $hasVisibleGrade) {
                    return [
                        'released' => false,
                        'student_status' => 'Belum dinilai',
                        'itemname' => $item['itemname'] ?? $assignment['name'] ?? 'Tugas',
                    ];
                }

                $feedback = $this->firstFilledString(
                    $submissionFeedback['feedback'] ?? null,
                    $this->gradeItemFeedback($item),
                    $assignmentGradeData['feedback'] ?? null,
                );

                return [
                    'released' => $hasVisibleGrade,
                    'student_status' => $hasVisibleGrade ? 'Dinilai' : 'Belum dinilai',
                    'itemname' => $item['itemname'] ?? $assignment['name'] ?? 'Tugas',
                    'grade' => $grade,
                    'gradeformatted' => $grade,
                    'feedback' => $feedback,
                ];
            }
        }

        return null;
    }

    protected function assignmentFeedbackFromSubmissionStatus(mixed $submissionStatus): array
    {
        if (! is_array($submissionStatus)) {
            return [];
        }

        $feedback = is_array($submissionStatus['feedback'] ?? null)
            ? $submissionStatus['feedback']
            : [];

        $grade = is_array($feedback['grade'] ?? null)
            ? $feedback['grade']
            : [];

        return [
            'grade' => $this->firstFilledString(
                $grade['grade'] ?? null,
                $feedback['grade'] ?? null,
            ),
            'gradeformatted' => $this->firstFilledString(
                $feedback['gradefordisplay'] ?? null,
                $feedback['gradeformatted'] ?? null,
                $grade['gradeformatted'] ?? null,
                $grade['str_grade'] ?? null,
            ),
            'feedback' => $this->firstFilledString(
                $feedback['feedback'] ?? null,
                $feedback['feedbackformatted'] ?? null,
                $this->submissionTextFromPlugins($feedback['plugins'] ?? []),
            ),
        ];
    }

    protected function gradeItemMatchesAssignment(array $item, int $assignmentId, int $cmid): bool
    {
        $itemModule = strtolower((string) ($item['itemmodule'] ?? ''));
        $itemInstance = (int) ($item['iteminstance'] ?? 0);
        $itemCmid = (int) ($item['cmid'] ?? 0);

        return $itemModule === 'assign'
            && (
                ($assignmentId > 0 && $itemInstance === $assignmentId)
                || ($cmid > 0 && $itemCmid === $cmid)
            );
    }

    protected function gradeItemIsHidden(array $item): bool
    {
        $hidden = $item['hidden'] ?? false;

        if (is_bool($hidden)) {
            return $hidden;
        }

        return in_array(strtolower((string) $hidden), ['1', 'true', 'yes', 'hidden'], true);
    }

    protected function gradeItemFeedback(array $item): ?string
    {
        return $this->firstFilledString(
            $item['feedback'] ?? null,
            $item['feedbackformatted'] ?? null,
            $item['feedbackstr'] ?? null,
            $item['feedbacktext'] ?? null,
            $item['feedbackhtml'] ?? null,
        );
    }

    protected function hasVisibleGrade(mixed $grade): bool
    {
        if ($grade === null) {
            return false;
        }

        $grade = trim(strip_tags((string) $grade));

        return $grade !== '' && $grade !== '-' && strtolower($grade) !== 'null';
    }

    protected function firstFilledString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $value = $this->firstFilledString(...array_values($value));
            }

            if ($value === null) {
                continue;
            }

            $text = trim(html_entity_decode(strip_tags((string) $value)));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    protected function assignmentGradesByUser(mixed $gradesResponse): array
    {
        if (! is_array($gradesResponse)) {
            return [];
        }

        $items = [];
        foreach (($gradesResponse['assignments'] ?? []) as $assignment) {
            foreach (($assignment['grades'] ?? []) as $grade) {
                $userId = (int) ($grade['userid'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $items[$userId] = [
                    'grade' => $grade['grade'] ?? null,
                    'gradeformatted' => $grade['gradeformatted'] ?? $grade['gradefordisplay'] ?? null,
                    'workflowstate' => $grade['workflowstate'] ?? null,
                    'feedback' => $this->firstFilledString(
                        $grade['feedback'] ?? null,
                        $grade['feedbackformatted'] ?? null,
                        $this->submissionTextFromPlugins($grade['plugins'] ?? []),
                    ),
                ];
            }
        }

        return $items;
    }

    protected function currentUserCanTeachCourse(int $courseId): bool
    {
        try {
            return $this->currentUserHasTeachingRole(
                $this->activeMoodleService()->getEnrolledUsers($courseId),
            );
        } catch (\Throwable) {
            return false;
        }
    }

    protected function currentUserHasTeachingRole(mixed $enrolledUsers): bool
    {
        if (! is_array($enrolledUsers)) {
            return false;
        }

        foreach ($enrolledUsers as $user) {
            if (! is_array($user) || ! $this->isCurrentMoodleUser($user)) {
                continue;
            }

            foreach (($user['roles'] ?? []) as $role) {
                $roleName = strtolower((string) ($role['shortname'] ?? $role['name'] ?? ''));

                if ($roleName === '') {
                    continue;
                }

                if (str_contains($roleName, 'student') || str_contains($roleName, 'mahasiswa')) {
                    continue;
                }

                if (
                    str_contains($roleName, 'teacher')
                    || str_contains($roleName, 'editingteacher')
                    || str_contains($roleName, 'manager')
                    || str_contains($roleName, 'coursecreator')
                    || str_contains($roleName, 'dosen')
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isCurrentMoodleUser(array $user): bool
    {
        $currentUser = session('moodle_user') ?? [];
        $currentId = (int) ($currentUser['id'] ?? 0);
        $currentUsername = strtolower((string) ($currentUser['moodle_username'] ?? session('username') ?? ''));
        $currentEmail = strtolower((string) ($currentUser['email'] ?? ''));

        if ($currentId > 0 && (int) ($user['id'] ?? 0) === $currentId) {
            return true;
        }

        if ($currentUsername !== '' && strtolower((string) ($user['username'] ?? '')) === $currentUsername) {
            return true;
        }

        return $currentEmail !== '' && strtolower((string) ($user['email'] ?? '')) === $currentEmail;
    }

    protected function submissionTextFromPlugins(array $plugins): ?string
    {
        foreach ($plugins as $plugin) {
            $pluginText = $this->firstFilledString(
                $plugin['text'] ?? null,
                $plugin['value'] ?? null,
                $plugin['content'] ?? null,
                $plugin['html'] ?? null,
            );

            if ($pluginText !== null) {
                return $pluginText;
            }

            foreach (($plugin['editorfields'] ?? []) as $field) {
                $fieldText = $this->firstFilledString(
                    $field['text'] ?? null,
                    $field['value'] ?? null,
                    $field['content'] ?? null,
                    $field['html'] ?? null,
                );

                if ($fieldText !== null) {
                    return $fieldText;
                }
            }

            foreach (($plugin['fields'] ?? []) as $field) {
                $fieldText = $this->firstFilledString(
                    $field['text'] ?? null,
                    $field['value'] ?? null,
                    $field['content'] ?? null,
                    $field['html'] ?? null,
                );

                if ($fieldText !== null) {
                    return $fieldText;
                }
            }
        }

        return null;
    }

    protected function assignmentWorkflowStates(): array
    {
        return [
            'notmarked' => 'Belum dinilai',
            'inmarking' => 'Sedang dinilai',
            'readyforreview' => 'Penilaian selesai',
            'inreview' => 'Sedang diulas',
            'readyforrelease' => 'Siap dirilis',
            'released' => 'Dirilis',
        ];
    }

    protected function submissionFilesFromPlugins(array $plugins): array
    {
        $files = [];

        foreach ($plugins as $plugin) {
            foreach (($plugin['fileareas'] ?? []) as $fileArea) {
                foreach (($fileArea['files'] ?? []) as $file) {
                    if (! empty($file['filename'])) {
                        $files[] = $file;
                    }
                }
            }
        }

        return $files;
    }

    protected function assignmentSubmissionSettings(array $assignment, mixed $submissionStatus): array
    {
        $configs = collect($assignment['configs'] ?? []);
        $configValue = function (string $name, ?string $plugin = null) use ($configs): ?string {
            $config = $configs->first(function ($item) use ($name, $plugin): bool {
                if (($item['name'] ?? null) !== $name) {
                    return false;
                }

                return $plugin === null || ($item['plugin'] ?? null) === $plugin;
            });

            return isset($config['value']) ? (string) $config['value'] : null;
        };

        $fileEnabled = $configValue('enabled', 'file');
        $maxFiles = (int) ($configValue('maxfilesubmissions', 'file') ?? $configValue('maxfilesubmissions') ?? 1);
        $maxFileSize = (int) ($configValue('maxsubmissionsizebytes', 'file') ?? $configValue('maxsubmissionsizebytes') ?? 20 * 1024 * 1024);
        $acceptedTypes = trim((string) ($configValue('filetypeslist', 'file') ?? $configValue('filetypeslist') ?? ''));
        $acceptedExtensions = $this->acceptedFileExtensions($acceptedTypes);
        $submission = is_array($submissionStatus) ? ($submissionStatus['lastattempt']['submission'] ?? []) : [];
        $lastAttempt = is_array($submissionStatus) ? ($submissionStatus['lastattempt'] ?? []) : [];
        $currentFiles = $this->countSubmissionFiles($submissionStatus);
        $currentDraftItemId = $this->submissionDraftItemId($submissionStatus);
        $submissionStatusText = strtolower((string) ($submission['status'] ?? ''));
        $isSubmitted = $submissionStatusText === 'submitted'
            || (isset($lastAttempt['canedit']) && ! (bool) $lastAttempt['canedit'] && $submissionStatusText !== 'new');
        $maxFiles = max(1, $maxFiles);

        return [
            'file_enabled' => $fileEnabled === null ? true : $fileEnabled === '1',
            'max_files' => $maxFiles,
            'current_files' => $currentFiles,
            'remaining_files' => max(0, $maxFiles - $currentFiles),
            'current_draft_item_id' => $currentDraftItemId,
            'submission_files' => $this->submissionFiles($submissionStatus),
            'max_file_size' => $maxFileSize > 0 ? $maxFileSize : 20 * 1024 * 1024,
            'max_file_size_label' => $this->humanFileSize($maxFileSize > 0 ? $maxFileSize : 20 * 1024 * 1024),
            'accepted_types_raw' => $acceptedTypes,
            'accepted_extensions' => $acceptedExtensions,
            'accepted_types_label' => $acceptedExtensions === [] ? 'Semua format yang diizinkan' : implode(' ', $acceptedExtensions),
            'accept_attribute' => implode(',', $acceptedExtensions),
            'is_submitted' => $isSubmitted,
            'submission_status' => $submission['status'] ?? null,
        ];
    }

    protected function assignmentDeadlineHasPassed(array $assignment, ?int $at = null): bool
    {
        $cutoffTimestamp = (int) ($assignment['cutoffdate'] ?? 0);
        $dueTimestamp = (int) ($assignment['duedate'] ?? 0);
        $deadlineTimestamp = $cutoffTimestamp > 0 ? $cutoffTimestamp : $dueTimestamp;

        return $deadlineTimestamp > 0 && ($at ?? time()) > $deadlineTimestamp;
    }

    /**
     * @param  array<int, mixed>  $statuses
     * @return array<int, mixed>
     */
    protected function withCompletedActivityStatus(array $statuses, int $moduleId, ?int $completedAt = null): array
    {
        $completedAt ??= time();

        foreach ($statuses as $index => $status) {
            if (! is_array($status)) {
                continue;
            }

            $statusModuleId = (int) ($status['cmid'] ?? $status['coursemoduleid'] ?? 0);
            if ($statusModuleId === $moduleId) {
                $statuses[$index]['state'] = 1;
                $statuses[$index]['completed'] = true;
                $statuses[$index]['timecompleted'] ??= $completedAt;

                return array_values($statuses);
            }
        }

        $statuses[] = [
            'cmid' => $moduleId,
            'state' => 1,
            'completed' => true,
            'timecompleted' => $completedAt,
        ];

        return array_values($statuses);
    }

    protected function reuploadSubmissionFilesToDraft(array $files): ?int
    {
        $draftItemId = null;

        foreach ($files as $file) {
            if (empty($file['fileurl']) || empty($file['filename'])) {
                continue;
            }

            $uploadResponse = $this->activeMoodleService()->uploadRemoteFileToDraft(
                $file['fileurl'],
                $file['filename'],
                $draftItemId,
            );
            $firstUpload = $uploadResponse[0] ?? $uploadResponse;
            $draftItemId = isset($firstUpload['itemid']) ? (int) $firstUpload['itemid'] : $draftItemId;

            if (! $draftItemId) {
                throw new \RuntimeException('Moodle tidak mengembalikan draft item ID saat mempertahankan file lama.');
            }
        }

        return $draftItemId;
    }

    protected function submissionFiles(mixed $submissionStatus): array
    {
        if (! is_array($submissionStatus)) {
            return [];
        }

        $files = [];
        foreach (($submissionStatus['lastattempt']['submission']['plugins'] ?? []) as $plugin) {
            foreach (($plugin['fileareas'] ?? []) as $fileArea) {
                foreach (($fileArea['files'] ?? []) as $file) {
                    if (! empty($file['filename'])) {
                        $files[] = $file;
                    }
                }
            }
        }

        return $files;
    }

    protected function submissionOnlineText(mixed $submissionStatus): ?string
    {
        if (! is_array($submissionStatus)) {
            return null;
        }

        foreach (($submissionStatus['lastattempt']['submission']['plugins'] ?? []) as $plugin) {
            foreach (($plugin['editorfields'] ?? []) as $field) {
                if (isset($field['text'])) {
                    return (string) $field['text'];
                }
            }
        }

        return null;
    }

    protected function assignmentSubmissionId(mixed $submissionStatus): ?int
    {
        if (! is_array($submissionStatus)) {
            return null;
        }

        $submissionId = (int) ($submissionStatus['lastattempt']['submission']['id'] ?? 0);

        return $submissionId > 0 ? $submissionId : null;
    }

    protected function latestAssignmentSubmissionComment(mixed $commentsResponse, int $userId): ?string
    {
        if (! is_array($commentsResponse)) {
            return null;
        }

        foreach (($commentsResponse['comments'] ?? []) as $comment) {
            if (! is_array($comment)) {
                continue;
            }

            if ($userId > 0 && (int) ($comment['userid'] ?? 0) !== $userId) {
                continue;
            }

            $content = $this->moodlePlainText($comment['content'] ?? '');
            if ($content !== '') {
                return $content;
            }
        }

        return null;
    }

    protected function countSubmissionFiles(mixed $submissionStatus): int
    {
        if (! is_array($submissionStatus)) {
            return 0;
        }

        $count = 0;
        foreach (($submissionStatus['lastattempt']['submission']['plugins'] ?? []) as $plugin) {
            foreach (($plugin['fileareas'] ?? []) as $fileArea) {
                $count += count($fileArea['files'] ?? []);
            }
        }

        return $count;
    }

    protected function submissionDraftItemId(mixed $submissionStatus): ?int
    {
        if (! is_array($submissionStatus)) {
            return null;
        }

        foreach (($submissionStatus['lastattempt']['submission']['plugins'] ?? []) as $plugin) {
            foreach (($plugin['fileareas'] ?? []) as $fileArea) {
                foreach (($fileArea['files'] ?? []) as $file) {
                    if (! empty($file['itemid'])) {
                        return (int) $file['itemid'];
                    }
                }
            }
        }

        return null;
    }

    protected function acceptedFileExtensions(string $acceptedTypes): array
    {
        if ($acceptedTypes === '') {
            return [];
        }

        $groups = [
            'document' => ['.doc', '.docx', '.epub', '.gdoc', '.odt', '.ott', '.oth', '.pdf', '.rtf'],
            'presentation' => ['.gslides', '.odp', '.otp', '.pps', '.ppt', '.pptx'],
            'spreadsheet' => ['.csv', '.gsheet', '.ods', '.ots', '.xls', '.xlsx'],
            'image' => ['.gif', '.jpe', '.jpeg', '.jpg', '.png', '.svg', '.webp'],
            'audio' => ['.aac', '.flac', '.m4a', '.mp3', '.oga', '.ogg', '.wav'],
            'video' => ['.avi', '.flv', '.m4v', '.mov', '.mp4', '.mpeg', '.mpg', '.ogv', '.webm'],
            'archive' => ['.7z', '.gz', '.rar', '.tar', '.tgz', '.zip'],
        ];

        $extensions = [];
        foreach (preg_split('/[\s,;]+/', strtolower($acceptedTypes)) ?: [] as $type) {
            $type = trim($type);
            if ($type === '') {
                continue;
            }

            if (isset($groups[$type])) {
                array_push($extensions, ...$groups[$type]);
                continue;
            }

            if (str_starts_with($type, '.')) {
                $extensions[] = $type;
            }
        }

        return array_values(array_unique($extensions));
    }

    protected function humanFileSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 0) . ' MB';
        }

        return number_format(max(1, $bytes / 1024), 0) . ' KB';
    }
}
