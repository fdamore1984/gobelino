<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceCommandController extends Controller
{
    /**
     * Queues a command for a device: it's picked up and executed at
     * its next poll (no push involved), and the outcome is reported
     * back in a later poll.
     */
    public function store(Request $request, Device $device): RedirectResponse
    {
        abort_unless($device->company_id === $request->user()->company_id, 403);
        abort_unless($device->isAndroid(), 422, 'Only Android devices support agent commands.');

        $data = $request->validate([
            'type' => ['required', Rule::in([
                DeviceCommand::TYPE_LOCK,
                DeviceCommand::TYPE_WIPE,
                DeviceCommand::TYPE_REBOOT,
                DeviceCommand::TYPE_SET_KIOSK,
            ])],
        ]);

        if ($data['type'] === DeviceCommand::TYPE_SET_KIOSK) {
            $device->update(['kiosk_enabled' => ! $device->kiosk_enabled]);

            $payload = ['enabled' => $device->kiosk_enabled];
        } else {
            $payload = null;
        }

        DeviceCommand::create([
            'device_id' => $device->id,
            'created_by' => $request->user()->id,
            'type' => $data['type'],
            'payload' => $payload,
        ]);

        return redirect()->route('devices.index')
            ->with('success', 'Command queued: it will be delivered at the device\'s next check-in.');
    }
}
