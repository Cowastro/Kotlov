<?php

namespace App\Http\Controllers;

use App\Models\PartnerBranch;
use App\Models\PartnerBranchContactView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerBranchContactController extends Controller
{
    /** The phone is revealed on demand so the centre knows which branch got the customer's interest. */
    public function __invoke(Request $request, PartnerBranch $branch): JsonResponse
    {
        abort_unless($branch->isPublic(), 404);
        $data = $request->validate(['product_id' => ['nullable', 'integer', 'exists:products,id']]);

        PartnerBranchContactView::query()->create([
            'partner_branch_id' => $branch->id,
            'product_id' => $data['product_id'] ?? null,
            'page' => isset($data['product_id']) ? 'product' : 'other',
        ]);

        return response()->json(['phones' => $branch->phoneLinks()]);
    }
}
