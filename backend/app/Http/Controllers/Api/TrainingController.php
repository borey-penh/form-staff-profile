<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = TrainingAssignment::with('training')
            ->where('user_id', $request->user()->id)
            ->orderBy('due_date')
            ->get()
            ->map(fn (TrainingAssignment $a) => [
                'id' => $a->id,
                'title' => $a->training->title,
                'description' => $a->training->description,
                'status' => $a->status,
                'progress' => $a->progress,
                'score' => $a->score,
                'dueDate' => $a->due_date?->toDateString(),
                'completedAt' => $a->completed_at?->toISOString(),
                'passScore' => $a->training->pass_score,
            ]);

        return response()->json(['data' => $items]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $assignment = TrainingAssignment::with('training')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $t = $assignment->training;

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'progress' => $assignment->progress,
                'score' => $assignment->score,
                'completedAt' => $assignment->completed_at?->toISOString(),
            ],
            'training' => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'sections' => $t->sections,
                'quiz' => collect($t->quiz)->map(fn ($q) => [
                    'question' => $q['question'],
                    'options' => $q['options'],
                    // answer intentionally omitted
                ]),
                'passScore' => $t->pass_score,
            ],
        ]);
    }

    /** Save reading progress (max section index reached). */
    public function progress(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['progress' => ['required', 'integer', 'min:0', 'max:100']]);

        $assignment = TrainingAssignment::where('user_id', $request->user()->id)->findOrFail($id);

        if ($assignment->status === 'Complete') {
            return response()->json(['message' => 'Already completed.']);
        }

        $assignment->update(['progress' => max($assignment->progress, $data['progress'])]);

        return response()->json(['message' => 'Progress saved.']);
    }

    /** Grade quiz; mark complete when passed. */
    public function submitQuiz(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['answers' => ['required', 'array']]);

        $assignment = TrainingAssignment::with('training')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($assignment->status === 'Complete') {
            return response()->json(['message' => 'Already completed.']);
        }

        $quiz = $assignment->training->quiz ?? [];
        $correct = 0;
        foreach ($quiz as $i => $q) {
            if (($data['answers'][$i] ?? null) === $q['answer']) {
                $correct++;
            }
        }

        $score = count($quiz) > 0 ? (int) round($correct / count($quiz) * 100) : 100;
        $passed = $score >= $assignment->training->pass_score;

        if ($passed) {
            $assignment->update([
                'status' => 'Complete',
                'progress' => 100,
                'score' => $score,
                'completed_at' => now(),
            ]);

            $request->user()->activities()->create([
                'icon' => 'training',
                'message' => "You completed {$assignment->training->title} training",
            ]);
        } else {
            $assignment->update(['score' => $score]);
        }

        return response()->json([
            'message' => $passed ? 'Training completed!' : 'Score below pass mark — try again.',
            'score' => $score,
            'passed' => $passed,
            'passScore' => $assignment->training->pass_score,
        ]);
    }
}
