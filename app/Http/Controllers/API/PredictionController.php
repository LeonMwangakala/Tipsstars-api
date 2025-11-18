<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PredictionController extends Controller
{
    /**
     * List tipster's own predictions
     */
    public function index()
    {
        $predictions = auth()->user()->predictions;
        return response()->json($predictions);
    }

    /**
     * Public listing of predictions
     */
    public function publicIndex(Request $request)
    {
        $predictions = Prediction::with('tipster')->latest()->paginate();
        return response()->json($predictions);
    }

    /**
     * Create a new prediction
     */
    public function store(Request $request)
    {
        // Check if tipster has open predictions that need result updates
        if (Prediction::tipsterHasOpenPredictions(auth()->id())) {
            return response()->json([
                'message' => 'You cannot create a new prediction while you have open predictions that need result updates. Please update the results of your existing predictions first.'
            ], 403);
        }

        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'image' => 'required|image',
            'booking_codes' => 'nullable|array',
            'odds_total' => 'nullable|numeric',
            'kickoff_at' => 'nullable|date',
            'kickend_at' => 'nullable|date|after:kickoff_at',
            'confidence_level' => 'nullable|integer',
            'is_premium' => 'boolean',
        ]);

        $path = $request->file('image')->store('slips', 'public');

        $prediction = Prediction::create([
            'tipster_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'image_url' => $path,
            'booking_codes' => $request->booking_codes,
            'odds_total' => $request->odds_total,
            'kickoff_at' => $request->kickoff_at,
            'kickend_at' => $request->kickend_at,
            'confidence_level' => $request->confidence_level,
            'is_premium' => $request->is_premium ?? true,
        ]);

        return response()->json($prediction);
    }

    /**
     * Update prediction
     */
    public function update(Request $request, $id)
    {
        $prediction = auth()->user()->predictions()->findOrFail($id);

        if ($prediction->isLocked()) {
            return response()->json(['message' => 'Prediction is locked and cannot be updated'], 403);
        }

        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'odds_total' => 'nullable|numeric',
            'kickoff_at' => 'required|date',
            'confidence_level' => 'nullable|integer',
            'is_premium' => 'boolean',
            'status' => 'required|in:draft,published,locked,graded',
            'result_status' => 'required|in:pending,win,loss,void,push,partial_win',
        ]);

        $prediction->update($request->all());

        return response()->json([
            'message' => 'Prediction updated successfully',
            'prediction' => $prediction,
        ]);
    }

    /**
     * Publish prediction
     */
    public function publish($id)
    {
        $prediction = auth()->user()->predictions()->findOrFail($id);

        if ($prediction->isLocked()) {
            return response()->json(['message' => 'Prediction is locked and cannot be published'], 403);
        }

        $prediction->update(['status' => 'published', 'publish_at' => now()]);

        return response()->json([
            'message' => 'Prediction published successfully',
            'prediction' => $prediction,
        ]);
    }

    /**
     * Lock prediction
     */
    public function lock($id)
    {
        $prediction = auth()->user()->predictions()->findOrFail($id);

        $prediction->update(['status' => 'locked', 'lock_at' => now()]);

        return response()->json([
            'message' => 'Prediction locked successfully',
            'prediction' => $prediction,
        ]);
    }

    /**
     * Grade prediction
     */
    public function grade(Request $request, $id)
    {
        $prediction = auth()->user()->predictions()->findOrFail($id);

        if (!$prediction->isLocked()) {
            return response()->json(['message' => 'Prediction should be locked before grading'], 403);
        }

        $request->validate([
            'result_status' => 'required|in:pending,won,lost,void',
            'result_notes' => 'nullable|string',
        ]);

        $prediction->update($request->only(['result_status', 'result_notes']));

        // Update tipster ratings after grading
        if (in_array($request->result_status, ['won', 'lost', 'void'])) {
            auth()->user()->updateRatings();
        }

        return response()->json([
            'message' => 'Prediction graded successfully',
            'prediction' => $prediction,
        ]);
    }

    /**
     * Upload prediction image
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $path = $request->file('image')->store('public/prediction_slips');

        return response()->json([
            'message' => 'Image uploaded successfully',
            'image_url' => Storage::url($path),
        ]);
    }

    /**
     * Get predictions that need result updates
     */
    public function getPredictionsNeedingResults()
    {
        $predictions = auth()->user()->predictions()
            ->needsResultUpdate()
            ->orderBy('kickoff_at', 'asc')
            ->get();

        return response()->json([
            'predictions' => $predictions
        ]);
    }

    /**
     * Update prediction result with winning slip
     */
    public function updateResult(Request $request, $id)
    {
        $prediction = auth()->user()->predictions()->findOrFail($id);

        if (!$prediction->needsResultUpdate()) {
            return response()->json([
                'message' => 'This prediction does not need a result update or has already been graded'
            ], 400);
        }

        $request->validate([
            'result_status' => 'required|in:won,lost,void,refunded',
            'result_notes' => 'nullable|string|max:1000',
            'winning_slip' => 'required_if:result_status,won|nullable|image|max:2048',
        ]);

        $winningSlipUrl = null;
        if ($request->hasFile('winning_slip')) {
            $winningSlipUrl = $request->file('winning_slip')->store('winning_slips', 'public');
        }

        $prediction->updateResult(
            $request->result_status,
            $request->result_notes,
            $winningSlipUrl
        );

        return response()->json([
            'message' => 'Prediction result updated successfully',
            'prediction' => $prediction->fresh(),
        ]);
    }

    /**
     * Upload winning slip image
     */
    public function uploadWinningSlip(Request $request)
    {
        $request->validate([
            'winning_slip' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $path = $request->file('winning_slip')->store('winning_slips', 'public');

        return response()->json([
            'message' => 'Winning slip uploaded successfully',
            'winning_slip_url' => Storage::url($path),
        ]);
    }
}
