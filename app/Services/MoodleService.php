<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use MoodleRest;
use RuntimeException;

class MoodleService
{
    public function __construct(
        protected ?string $token = null,
        protected ?string $baseUrl = null,
    ) {
        $this->token = $token ?? config('moodle.token');
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('moodle.base_url')), '/');
    }

    public function fetchToken(
        ?string $username = null,
        ?string $password = null,
        ?string $serviceName = null,
    ): array {
        $baseUrl = $this->requireBaseUrl();
        $username ??= config('moodle.username');
        $password ??= config('moodle.password');
        $serviceName ??= config('moodle.service_name');

        if (! $username || ! $password || ! $serviceName) {
            throw new InvalidArgumentException('Username, password, and service name are required to fetch a Moodle token.');
        }

        $request = Http::asForm();

        if (! (bool) config('moodle.verify_ssl', true)) {
            $request = $request->withoutVerifying();
        }

        $response = $request
            ->post($baseUrl . '/login/token.php', [
                'username' => $username,
                'password' => $password,
                'service' => $serviceName,
            ])
            ->throw()
            ->json();

        if (! isset($response['token'])) {
            throw new RuntimeException($response['error'] ?? 'Failed to fetch Moodle token.');
        }

        return $response;
    }

    public function getUserByUsername(string $username): ?array
    {
        $users = $this->request('core_user_get_users_by_field', [
            'field' => 'username',
            'values' => [$username],
        ]);

        return $users[0] ?? null;
    }

    public function getUserCourses(int $userId): mixed
    {
        return $this->request('core_enrol_get_users_courses', [
            'userid' => $userId,
        ]);
    }

    public function getUserGrades(int $courseId, ?int $userId = null): array
    {
        $parameters = [
            'courseid' => $courseId,
        ];

        if ($userId !== null) {
            $parameters['userid'] = $userId;
        }

        $gradesReport = $this->request(
            'gradereport_user_get_grade_items',
            $parameters
        );

        $userGrades = $gradesReport['usergrades'] ?? [];

        $gradeItems = [];

        if (! empty($userGrades[0]['gradeitems'])) {
            foreach ($userGrades[0]['gradeitems'] as $item) {
                $gradeItems[] = [
                    'id' => $item['id'] ?? null,
                    'itemtype' => $item['itemtype'] ?? null,
                    'itemname' => $item['itemname'] ?? null,
                    'itemmodule' => $item['itemmodule'] ?? null,
                    'iteminstance' => $item['iteminstance'] ?? null,
                    'cmid' => $item['cmid'] ?? null,
                    'grade' => $item['grade'] ?? null,
                    'gradeformatted' => $item['gradeformatted'] ?? null,
                    'gradefordisplay' => $item['gradefordisplay'] ?? null,
                    'str_grade' => $item['str_grade'] ?? null,
                    'graderaw' => $item['graderaw'] ?? null,
                    'grademin' => $item['grademin'] ?? null,
                    'grademax' => $item['grademax'] ?? null,
                    'rangeformatted' => $item['rangeformatted'] ?? null,
                    'gradedatesubmitted' => $item['gradedatesubmitted'] ?? null,
                    'gradedategraded' => $item['gradedategraded'] ?? null,
                    'datesubmitted' => $item['datesubmitted'] ?? null,
                    'timesubmitted' => $item['timesubmitted'] ?? null,
                    'timecreated' => $item['timecreated'] ?? null,
                    'timemodified' => $item['timemodified'] ?? null,
                    'feedback' => $item['feedback'] ?? null,
                    'hidden' => $item['hidden'] ?? null,
                ];
            }
        }

        return [
            'gradeitems' => $gradeItems,
            'usergrades' => $userGrades,
        ];
    }

    public function getEnrolledUsers(int $courseId): mixed
    {
        return $this->request('core_enrol_get_enrolled_users', [
            'courseid' => $courseId,
        ]);
    }

    public function createGradeCategory(int $courseId, string $categoryName, array $options = []): mixed
    {
        return $this->request('core_grades_create_gradecategories', [
            'courseid' => $courseId,
            'categories' => [
                [
                    'fullname' => $categoryName,
                    'options' => $options,
                ],
            ],
        ]);
    }

    public function enrolSelfEnrolUser(int $courseId, int $userId, string $enrolmentKey = ''): mixed
    {
        return $this->request('enrol_self_enrol_user', [
            'courseid' => $courseId,
            'userid' => $userId,
            'enrolpassword' => $enrolmentKey,
        ]);
    }

    public function selfEnrollIntoCourse(int $userId, int $courseId, string $password): mixed
    {
        $lastThrowable = null;

        foreach (
            [
                fn() => $this->request('enrol_self_enrol_user', [
                    'courseid' => $courseId,
                    'password' => $password,
                ], MoodleRest::METHOD_POST),
                fn() => $this->request('uad_create_selfenrol', [
                    'courseid' => $courseId,
                    'password' => $password,
                    'userid' => $userId,
                ], MoodleRest::METHOD_POST),
            ] as $attempt
        ) {
            try {
                $response = $attempt();
                $this->assertSelfEnrollSucceeded($response);

                return $response;
            } catch (\Throwable $throwable) {
                if ($this->isWrongSelfEnrollPassword($throwable->getMessage())) {
                    throw new InvalidArgumentException('Password kursus salah.');
                }

                $lastThrowable = $throwable;
            }
        }

        throw $lastThrowable ?? new RuntimeException('Pendaftaran kursus gagal.');
    }

    public function registerUser(array $userData): mixed
    {
        return $this->request('core_user_create_users', [
            'users' => [$userData],
        ]);
    }

    public function getAllUserByIds(array $userIds = []): mixed
    {
        return $this->request('core_user_get_users', [
            'criteria' => [
                [
                    'key' => 'username',
                    'value' => implode(',', $userIds),
                ],
            ],
        ]);
    }

    public function getUser(string $key, string $value): ?array
    {
        $response = $this->request('core_user_get_users', [
            'criteria' => [
                [
                    'key' => $key,
                    'value' => $value,
                ],
            ],
        ]);

        return $response['users'][0] ?? null;
    }

    public function getStudentsInCourse(int $courseId): mixed
    {
        return $this->request('core_enrol_get_enrolled_users', [
            'courseid' => $courseId,
        ]);
    }

    public function getCourses(): mixed
    {
        return $this->request('core_course_get_courses');
    }

    public function searchCourses(string $query, int $page = 0, int $perPage = 20): mixed
    {
        return $this->request('core_course_search_courses', [
            'criterianame' => 'search',
            'criteriavalue' => $query,
            'page' => $page,
            'perpage' => $perPage,
            'requiredcapabilities' => [],
            'limittoenrolled' => 0,
        ]);
    }

    public function getCourse(int $courseId): ?array
    {
        $response = $this->request('core_course_get_courses_by_field', [
            'field' => 'id',
            'value' => $courseId,
        ]);

        return $response['courses'][0] ?? null;
    }

    public function getCourseContents(int $courseId): mixed
    {
        return $this->request('core_course_get_contents', [
            'courseid' => $courseId,
        ]);
    }

    public function getResources(int $courseId): mixed
    {
        return $this->request('mod_resource_get_resources_by_courses', [
            'courseids' => [$courseId],
        ]);
    }

    public function getCalendarActionEventsByCourses(array $courseIds): mixed
    {
        return $this->request('core_calendar_get_action_events_by_courses', [
            'courseids' => array_values(array_filter($courseIds)),
        ]);
    }

    public function getActivityCompletionStatus(int $courseId, int $userId): mixed
    {
        return $this->request('core_completion_get_activities_completion_status', [
            'courseid' => $courseId,
            'userid' => $userId,
        ]);
    }

    public function getRecentlyAccessedItems(): mixed
    {
        return $this->request('block_recentlyaccesseditems_get_recent_items', [
            'limit' => 2,
        ]);
    }

    public function markActivityComplete(int $courseModuleId): mixed
    {
        return $this->request('core_completion_update_activity_completion_status_manually', [
            'cmid' => $courseModuleId,
            'completed' => 1,
        ], MoodleRest::METHOD_POST);
    }

    public function getAssignments(int $courseId): mixed
    {
        return $this->request('mod_assign_get_assignments', [
            'courseids' => [$courseId],
        ]);
    }

    public function getQuizzes(int $courseId): mixed
    {
        return $this->request('mod_quiz_get_quizzes_by_courses', [
            'courseids' => [$courseId],
        ]);
    }

    public function getQuizUserAttempts(int $quizId, int $userId, string $status = 'all'): mixed
    {
        return $this->request('mod_quiz_get_user_attempts', [
            'quizid' => $quizId,
            'userid' => $userId,
            'status' => $status,
            'includepreviews' => 0,
        ]);
    }

    public function startQuizAttempt(int $quizId): mixed
    {
        $lastThrowable = null;

        foreach (
            [
                ['quizid' => $quizId],
                ['quizid' => $quizId, 'preflightdata' => []],
                ['quizid' => $quizId, 'forcenew' => false],
                ['quizid' => $quizId, 'forcenew' => 0, 'preflightdata' => []],
            ] as $parameters
        ) {
            try {
                return $this->request('mod_quiz_start_attempt', $parameters, MoodleRest::METHOD_POST);
            } catch (\Throwable $throwable) {
                $lastThrowable = $throwable;
            }
        }

        throw $lastThrowable ?? new RuntimeException('Attempt kuis gagal dimulai.');
    }

    public function getQuizAttemptData(int $attemptId, int $page = 0): mixed
    {
        return $this->request('mod_quiz_get_attempt_data', [
            'attemptid' => $attemptId,
            'page' => $page,
        ]);
    }

    public function getQuizAttemptSummary(int $attemptId): mixed
    {
        return $this->request('mod_quiz_get_attempt_summary', [
            'attemptid' => $attemptId,
        ]);
    }

    public function getQuizAttemptReview(int $attemptId): mixed
    {
        return $this->request('mod_quiz_get_attempt_review', [
            'attemptid' => $attemptId,
            'page' => -1,
        ]);
    }

    public function processQuizAttempt(int $attemptId, array $data, bool $finishAttempt = false): mixed
    {
        return $this->request('mod_quiz_process_attempt', [
            'attemptid' => $attemptId,
            'data' => $data,
            'finishattempt' => $finishAttempt ? 1 : 0,
            'timeup' => 0,
        ], MoodleRest::METHOD_POST);
    }

    public function getAssignmentSubmissionStatus(int $assignmentId): mixed
    {
        return $this->request('mod_assign_get_submission_status', [
            'assignid' => $assignmentId,
        ]);
    }

    public function getAssignmentSubmissions(int $assignmentId, string $status = 'submitted'): mixed
    {
        $parameters = [
            'assignmentids' => [$assignmentId],
        ];

        if ($status !== '') {
            $parameters['status'] = $status;
        }

        return $this->request('mod_assign_get_submissions', $parameters);
    }

    public function getAssignmentGrades(int $assignmentId): mixed
    {
        return $this->request('mod_assign_get_grades', [
            'assignmentids' => [$assignmentId],
        ]);
    }

    public function uploadDraftFile(UploadedFile $file, ?int $itemId = null): array
    {
        return $this->uploadFilePathToDraft(
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $itemId,
        );
    }

    public function uploadRemoteFileToDraft(string $fileUrl, string $filename, ?int $itemId = null): array
    {
        $url = $fileUrl;
        if (! str_contains($url, 'token=')) {
            $url .= str_contains($url, '?') ? '&token=' . urlencode($this->requireToken()) : '?token=' . urlencode($this->requireToken());
        }

        $request = Http::timeout(60);
        if (! (bool) config('moodle.verify_ssl', true)) {
            $request = $request->withoutVerifying();
        }

        $response = $request->get($url)->throw();
        $temporaryPath = tempnam(sys_get_temp_dir(), 'moodle-draft-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Gagal membuat file sementara untuk draft Moodle.');
        }

        file_put_contents($temporaryPath, $response->body());

        try {
            return $this->uploadFilePathToDraft($temporaryPath, $filename, $itemId);
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function uploadFilePathToDraft(string $path, string $filename, ?int $itemId = null): array
    {
        $request = Http::attach(
            'file',
            fopen($path, 'r'),
            $filename,
        );

        if (! (bool) config('moodle.verify_ssl', true)) {
            $request = $request->withoutVerifying();
        }

        $response = $request
            ->post($this->requireBaseUrl() . '/webservice/upload.php', [
                'token' => $this->requireToken(),
                'filearea' => 'draft',
                'itemid' => $itemId ?? 0,
                'filepath' => '/',
                'filename' => $filename,
            ])
            ->throw()
            ->json();

        if (! is_array($response) || isset($response['exception'])) {
            throw new RuntimeException($response['message'] ?? 'File gagal diupload ke Moodle.');
        }

        return $response;
    }

    public function saveAssignmentSubmission(int $assignmentId, ?string $answer = null, ?int $draftItemId = null): mixed
    {
        $pluginData = [];

        if ($answer !== null && trim($answer) !== '') {
            $pluginData['onlinetext_editor'] = [
                'text' => $answer,
                'format' => 1,
                'itemid' => 0,
            ];
        }

        if ($draftItemId !== null) {
            $pluginData['files_filemanager'] = $draftItemId;
        }

        if ($pluginData === []) {
            throw new InvalidArgumentException('Jawaban teks atau file wajib diisi.');
        }

        return $this->request('mod_assign_save_submission', [
            'assignmentid' => $assignmentId,
            'plugindata' => $pluginData,
        ], MoodleRest::METHOD_POST);
    }

    public function clearAssignmentSubmissionDraft(int $assignmentId): mixed
    {
        return $this->request('mod_assign_save_submission', [
            'assignmentid' => $assignmentId,
            'plugindata' => [
                'files_filemanager' => 0,
                'onlinetext_editor' => [
                    'text' => '',
                    'format' => 1,
                    'itemid' => 0,
                ],
            ],
        ], MoodleRest::METHOD_POST);
    }

    public function submitAssignmentForGrading(int $assignmentId, bool $acceptStatement = false): mixed
    {
        return $this->request('mod_assign_submit_for_grading', [
            'assignmentid' => $assignmentId,
            'acceptsubmissionstatement' => $acceptStatement ? 1 : 0,
        ], MoodleRest::METHOD_POST);
    }

    public function saveAssignmentGrade(
        int $assignmentId,
        int $userId,
        float $grade,
        ?string $feedback = null,
        ?string $workflowState = null,
    ): mixed {
        $pluginData = [];
        $workflowState = trim((string) $workflowState);

        if ($feedback !== null && trim($feedback) !== '') {
            $pluginData['assignfeedbackcomments_editor'] = [
                'text' => $feedback,
                'format' => 1,
            ];
        }

        return $this->request('mod_assign_save_grade', [
            'assignmentid' => $assignmentId,
            'userid' => $userId,
            'grade' => $grade,
            'attemptnumber' => -1,
            'addattempt' => 0,
            'workflowstate' => $workflowState,
            'applytoall' => 0,
            'plugindata' => $pluginData,
        ], MoodleRest::METHOD_POST);
    }

    public function getCategories(): mixed
    {
        return $this->request('core_course_get_categories');
    }

    public function addStudentToCourse(int $studentId, int $courseId): mixed
    {
        return $this->request('enrol_manual_enrol_users', [
            'enrolments' => [
                [
                    'roleid' => 5,
                    'userid' => $studentId,
                    'courseid' => $courseId,
                ],
            ],
        ]);
    }

    public function getCourseCategories(string $categoryId): mixed
    {
        return $this->request('core_course_get_categories', [
            'criteria' => [
                [
                    'key' => 'idnumber',
                    'value' => $categoryId,
                ],
            ],
        ]);
    }

    public function getCourseGradeCategories(int $courseId): mixed
    {
        return $this->request('uad_get_gradecategories_course', [
            'courseid' => $courseId,
        ]);
    }

    public function getGradeReportCourse(int $courseId): mixed
    {
        return $this->request('uad_get_gradereport', [
            'courseid' => $courseId,
        ]);
    }

    public function getGradeReportSelfEnrol(int $courseId, string $password): mixed
    {
        return $this->request('uad_get_gradereport_selfenrol', [
            'courseid' => $courseId,
            'password' => $password,
        ]);
    }

    public function getSelfEnrolCourse(int $courseId, string $password): mixed
    {
        return $this->request('uad_get_selfenrol', [
            'courseid' => $courseId,
            'password' => $password,
        ]);
    }

    public function createStudentSelfEnrollToCourse(int $userId, int $courseId, string $password): mixed
    {
        return $this->request('uad_create_selfenrol', [
            'courseid' => $courseId,
            'password' => $password,
            'userid' => $userId,
        ]);
    }

    protected function assertSelfEnrollSucceeded(mixed $response): void
    {
        if (! is_array($response)) {
            return;
        }

        $status = $response['status'] ?? $response['success'] ?? null;
        if ($status === false || $status === 0 || $status === '0' || $status === 'false') {
            $message = (string) ($response['message'] ?? $response['error'] ?? 'Pendaftaran kursus gagal.');

            if ($this->isWrongSelfEnrollPassword($message)) {
                throw new InvalidArgumentException('Password kursus salah.');
            }

            throw new RuntimeException($message);
        }

        foreach (($response['warnings'] ?? []) as $warning) {
            $message = (string) ($warning['message'] ?? $warning['warning'] ?? '');
            if ($this->isWrongSelfEnrollPassword($message)) {
                throw new InvalidArgumentException('Password kursus salah.');
            }

            if ($message !== '') {
                throw new RuntimeException($message);
            }
        }
    }

    protected function isWrongSelfEnrollPassword(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'password')
            || str_contains($message, 'enrolment key')
            || str_contains($message, 'enrolmentkey')
            || str_contains($message, 'invalidkey')
            || str_contains($message, 'key holder')
            || str_contains($message, 'kunci')
            || str_contains($message, 'salah');
    }

    protected function request(string $function, array $parameters = [], string $method = MoodleRest::METHOD_GET): mixed
    {
        $response = $this->client()->request($function, $parameters, $method);

        if (is_array($response) && isset($response['exception'])) {
            throw new RuntimeException($response['message'] ?? $response['errorcode'] ?? 'Moodle request failed.');
        }

        return $response;
    }

    protected function client(): MoodleRest
    {
        return new MoodleRest(
            $this->serverAddress(),
            $this->requireToken(),
            MoodleRest::RETURN_ARRAY,
        );
    }

    protected function serverAddress(): string
    {
        return $this->requireBaseUrl() . '/webservice/rest/server.php';
    }

    protected function requireBaseUrl(): string
    {
        if (! $this->baseUrl) {
            throw new RuntimeException('Moodle base URL is not configured. Set MOODLE_BASE_URL in your environment.');
        }

        return $this->baseUrl;
    }

    protected function requireToken(): string
    {
        if ($this->token) {
            return $this->token;
        }

        $tokenResponse = $this->fetchToken();

        $this->token = $tokenResponse['token'];

        return $this->token;
    }
}
