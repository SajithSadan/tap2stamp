<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffMemberRequest;
use App\Http\Requests\UpdateStaffPinRequest;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Owner-managed staff accounts. The owner sets each PIN and tells the staff
 * member in person - there's no self-service PIN change on the device.
 */
class StaffMemberController extends Controller
{
    public function store(StoreStaffMemberRequest $request): RedirectResponse
    {
        $request->user()->shop->staffMembers()->create([
            'name' => $request->string('name')->trim()->value(),
            'pin_hash' => Hash::make($request->string('pin')->value()),
        ]);

        return redirect()->route('dashboard.staff');
    }

    public function updatePin(UpdateStaffPinRequest $request, StaffMember $staffMember): RedirectResponse
    {
        $this->authorizeOwner($request, $staffMember);

        $staffMember->update(['pin_hash' => Hash::make($request->string('pin')->value())]);

        return redirect()->route('dashboard.staff');
    }

    /**
     * Deactivates rather than deletes, so past stamps keep their name - and
     * signs them out of any device they're on right now.
     */
    public function destroy(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $this->authorizeOwner($request, $staffMember);

        $staffMember->update(['deactivated_at' => now()]);
        $staffMember->shop->staffDevices()
            ->where('staff_member_id', $staffMember->id)
            ->update(['staff_member_id' => null, 'staff_signed_in_at' => null]);

        return redirect()->route('dashboard.staff');
    }

    private function authorizeOwner(Request $request, StaffMember $staffMember): void
    {
        abort_unless($staffMember->shop_id === $request->user()->shop->id, 403);
    }
}
