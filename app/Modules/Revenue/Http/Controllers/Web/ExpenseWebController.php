<?php
namespace App\Modules\Revenue\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExpenseWebController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'category'     => 'required|in:tools,freelancer,office,ads,travel,hardware,other',
            'amount'       => 'required|numeric|min:0',
            'currency'     => 'nullable|string|size:3',
            'expense_date' => 'required|date',
            'vendor'       => 'nullable|string|max:255',
            'notes'        => 'nullable|string|max:1000',
            'is_recurring' => 'boolean',
            'recurrence'   => 'nullable|in:monthly,annual,one_time',
        ]);

        Expense::create([
            'organization_id' => $request->user()->organization_id,
            'currency'        => $validated['currency'] ?? 'INR',
            ...$validated,
        ]);

        return back()->with('success', 'Expense recorded.');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeOrg($expense);

        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'category'     => 'sometimes|in:tools,freelancer,office,ads,travel,hardware,other',
            'amount'       => 'sometimes|numeric|min:0',
            'expense_date' => 'sometimes|date',
            'vendor'       => 'sometimes|nullable|string|max:255',
            'notes'        => 'sometimes|nullable|string|max:1000',
        ]);

        $expense->update($validated);
        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeOrg($expense);
        $expense->delete();
        return back()->with('success', 'Expense deleted.');
    }
}
