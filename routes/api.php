<?php

use App\Http\Controllers\Api\MoodleController;
use Illuminate\Support\Facades\Route;

Route::prefix('moodle')->controller(MoodleController::class)->group(function (): void {
    Route::post('/token', 'fetchToken');

    Route::get('/users', 'getUser');
    Route::get('/users/by-username/{username}', 'getUserByUsername');
    Route::get('/users/by-usernames', 'getAllUserByIds');
    Route::post('/users/register', 'registerUser');
    Route::get('/users/{userId}/courses', 'getUserCourses');

    Route::get('/courses', 'getCourses');
    Route::get('/courses/{courseId}', 'getCourse');
    Route::get('/courses/{courseId}/students', 'getStudentsInCourse');
    Route::post('/courses/{courseId}/students/{studentId}/enrol', 'addStudentToCourse');
    Route::get('/courses/{courseId}/enrolled-users', 'getEnrolledUsers');
    Route::get('/courses/{courseId}/grades', 'getUserGrades');
    Route::get('/courses/{courseId}/grade-categories', 'getCourseGradeCategories');
    Route::post('/courses/{courseId}/grade-categories', 'createGradeCategory');
    Route::get('/courses/{courseId}/grade-report', 'getGradeReportCourse');
    Route::get('/courses/{courseId}/grade-report/self-enrol', 'getGradeReportSelfEnrol');
    Route::get('/courses/{courseId}/self-enrol', 'getSelfEnrolCourse');
    Route::post('/courses/{courseId}/self-enrol', 'createStudentSelfEnrollToCourse');
    Route::post('/courses/{courseId}/self-enrol-user', 'enrolSelfEnrolUser');

    Route::get('/categories', 'getCategories');
    Route::get('/course-categories/{categoryId}', 'getCourseCategories');
});
