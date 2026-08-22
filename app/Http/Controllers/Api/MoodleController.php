<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MoodleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MoodleController extends Controller
{
    public function __construct(
        protected MoodleService $moodleService,
    ) {
    }

    public function fetchToken(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'service' => ['nullable', 'string'],
        ]);

        return $this->respond(fn () => $this->moodleService->fetchToken(
            $payload['username'] ?? null,
            $payload['password'] ?? null,
            $payload['service'] ?? null,
        ));
    }

    public function getUser(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['required', 'string'],
        ]);

        return $this->respond(fn () => $this->moodleService->getUser(
            $payload['key'],
            $payload['value'],
        ));
    }

    public function getUserByUsername(string $username): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getUserByUsername($username));
    }

    public function getUserCourses(int $userId): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getUserCourses($userId));
    }

    public function getUserGrades(int $courseId): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getUserGrades($courseId));
    }

    public function getEnrolledUsers(int $courseId): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getEnrolledUsers($courseId));
    }

    public function createGradeCategory(Request $request, int $courseId): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string'],
            'options' => ['sometimes', 'array'],
        ]);

        return $this->respond(fn () => $this->moodleService->createGradeCategory(
            $courseId,
            $payload['name'],
            $payload['options'] ?? [],
        ));
    }

    public function enrolSelfEnrolUser(Request $request, int $courseId): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['required', 'integer'],
            'enrolment_key' => ['sometimes', 'string'],
        ]);

        return $this->respond(fn () => $this->moodleService->enrolSelfEnrolUser(
            $courseId,
            (int) $payload['user_id'],
            $payload['enrolment_key'] ?? '',
        ));
    }

    public function registerUser(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'firstname' => ['required', 'string'],
            'lastname' => ['required', 'string'],
            'email' => ['required', 'email'],
            'auth' => ['sometimes', 'string'],
            'idnumber' => ['sometimes', 'string'],
            'lang' => ['sometimes', 'string'],
            'timezone' => ['sometimes', 'string'],
        ]);

        return $this->respond(fn () => $this->moodleService->registerUser($payload), 201);
    }

    public function getAllUserByIds(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'usernames' => ['required', 'array'],
            'usernames.*' => ['string'],
        ]);

        return $this->respond(fn () => $this->moodleService->getAllUserByIds($payload['usernames']));
    }

    public function getStudentsInCourse(int $courseId): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getStudentsInCourse($courseId));
    }

    public function getCourses(): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getCourses());
    }

    public function getCourse(int $courseId): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getCourse($courseId));
    }

    public function getCategories(): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getCategories());
    }

    public function addStudentToCourse(int $courseId, int $studentId): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->addStudentToCourse($studentId, $courseId));
    }

    public function getCourseCategories(string $categoryId): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getCourseCategories($categoryId));
    }

    public function getCourseGradeCategories(int $courseId): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getCourseGradeCategories($courseId));
    }

    public function getGradeReportCourse(int $courseId): JsonResponse
    {
        return $this->respond(fn () => $this->moodleService->getGradeReportCourse($courseId));
    }

    public function getGradeReportSelfEnrol(Request $request, int $courseId): JsonResponse
    {
        $payload = $request->validate([
            'password' => ['required', 'string'],
        ]);

        return $this->respond(fn () => $this->moodleService->getGradeReportSelfEnrol(
            $courseId,
            $payload['password'],
        ));
    }

    public function getSelfEnrolCourse(Request $request, int $courseId): JsonResponse
    {
        $payload = $request->validate([
            'password' => ['required', 'string'],
        ]);

        return $this->respond(fn () => $this->moodleService->getSelfEnrolCourse(
            $courseId,
            $payload['password'],
        ));
    }

    public function createStudentSelfEnrollToCourse(Request $request, int $courseId): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['required', 'integer'],
            'password' => ['required', 'string'],
        ]);

        return $this->respond(fn () => $this->moodleService->createStudentSelfEnrollToCourse(
            (int) $payload['user_id'],
            $courseId,
            $payload['password'],
        ), 201);
    }

    protected function respond(callable $callback, int $successStatus = 200): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $callback(),
            ], $successStatus);
        } catch (Throwable $throwable) {
            return response()->json([
                'success' => false,
                'message' => $throwable->getMessage(),
            ], 422);
        }
    }

}
