<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceType;
use App\Services\MobileApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

class DeviceCatalogController extends Controller
{
    public function index(): View
    {
        return view('devices.index', [
            'searchResults' => [],
            'searchedName' => '',
            'savedMobileApiIds' => [],
            'selectedDevice' => Device::query()->with('marketValues')->find(session('selected_device_id')),
            'savedDevices' => Device::query()->with('marketValues')->latest()->paginate(9),
            'deviceTypes' => DeviceType::query()->where('is_active', true)->orderBy('platform')->get(),
        ]);
    }

    public function search(Request $request, MobileApiService $mobileApiService): View|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $searchResults = $mobileApiService->searchDevices($validated['name']);
        } catch (ConnectionException|RequestException|LogicException|UnexpectedValueException $exception) {
            Log::warning('MobileAPI device search failed.', ['exception' => $exception]);

            return redirect()
                ->route('devices.index')
                ->withInput()
                ->withErrors(['catalog' => 'Katalog perangkat sedang tidak dapat diakses. Coba lagi beberapa saat.']);
        }

        return view('devices.index', [
            'searchResults' => $searchResults,
            'searchedName' => $validated['name'],
            'savedMobileApiIds' => Device::query()
                ->whereIn('mobile_api_id', collect($searchResults)->pluck('mobile_api_id')->filter()->all())
                ->pluck('mobile_api_id')
                ->all(),
            'selectedDevice' => null,
            'savedDevices' => Device::query()->with('marketValues')->latest()->paginate(9),
            'deviceTypes' => DeviceType::query()->where('is_active', true)->orderBy('platform')->get(),
        ]);
    }

    public function select(Request $request, MobileApiService $mobileApiService): RedirectResponse
    {
        $validated = $request->validate([
            'mobile_api_id' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $existingDevice = Device::query()
            ->where('mobile_api_id', $validated['mobile_api_id'])
            ->first();

        if ($existingDevice !== null) {
            return redirect()
                ->route('devices.index')
                ->with('selected_device_id', $existingDevice->id)
                ->with('status', 'Perangkat ini sudah tersimpan di katalog.');
        }

        try {
            $searchResults = $mobileApiService->searchDevices($validated['name']);
        } catch (ConnectionException|RequestException|LogicException|UnexpectedValueException $exception) {
            Log::warning('MobileAPI device selection lookup failed.', ['exception' => $exception]);

            return redirect()
                ->route('devices.index')
                ->withInput()
                ->withErrors(['catalog' => 'Katalog perangkat sedang tidak dapat diakses. Coba lagi beberapa saat.']);
        }

        $selectedDeviceData = collect($searchResults)->first(
            fn (array $device): bool => $device['mobile_api_id'] === $validated['mobile_api_id']
        );

        if ($selectedDeviceData === null) {
            return redirect()
                ->route('devices.index')
                ->withInput()
                ->withErrors(['catalog' => 'Perangkat tidak lagi tersedia dari katalog. Silakan cari ulang.']);
        }

        try {
            $device = $mobileApiService->saveDevice($selectedDeviceData);
        } catch (InvalidArgumentException $exception) {
            Log::warning('MobileAPI device selection returned incomplete device data.', ['exception' => $exception]);

            return redirect()
                ->route('devices.index')
                ->withInput()
                ->withErrors(['catalog' => 'Data perangkat tidak lengkap sehingga tidak dapat disimpan.']);
        }

        return redirect()
            ->route('devices.index')
            ->with('selected_device_id', $device->id)
            ->with('status', 'Perangkat berhasil disimpan ke katalog.');
    }
}
