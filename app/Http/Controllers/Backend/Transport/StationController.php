<?php

namespace App\Http\Controllers\Backend\Transport;

use App\Exceptions\HafasException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HafasController;
use App\Models\Checkin;
use App\Models\Station;
use App\Models\Stopover;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

abstract class StationController extends Controller
{

    /**
     * @throws HafasException
     * @throws ModelNotFoundException
     */
    public static function lookupStation(string|int $query): Station {
        //Lookup by station ibnr
        if (is_numeric($query)) {
            $station = Station::where('ibnr', $query)->first();
            if ($station !== null) {
                return $station;
            }
        }

        //Lookup by ril identifier
        if (!is_numeric($query) && strlen($query) <= 5 && ctype_upper($query)) {
            $station = HafasController::getStationByRilIdentifier($query);
            if ($station !== null) {
                return $station;
            }
        }

        //Lookup HAFAS
        $station = HafasController::getStations(query: $query, results: 1)->first();
        if ($station !== null) {
            return $station;
        }

        throw new ModelNotFoundException;
    }

    /**
     * Get the latest Stations the user is arrived.
     *
     * @param User $user
     * @param int  $maxCount
     *
     * @return Collection
     */
    public static function getLatestArrivals(User $user, int $maxCount = 5): Collection {
        $groupAndSelect = [
            'train_stations.id', 'train_stations.ibnr', 'train_stations.name',
            'train_stations.latitude', 'train_stations.longitude', 'train_stations.rilIdentifier',
        ];
        return DB::table('train_checkins') //TODO: return Station objects
                 ->join('train_stopovers', 'train_checkins.destination_stopover_id', '=', 'train_stopovers.id')
                 ->join('train_stations', 'train_stopovers.train_station_id', '=', 'train_stations.id')
                 ->where('train_checkins.user_id', $user->id)
                 ->groupBy($groupAndSelect)
                 ->select($groupAndSelect)
                 ->orderByDesc(DB::raw('MAX(train_checkins.arrival)'))
                 ->limit($maxCount)
                 ->get();
    }

    public static function getAlternativeDestinationsForCheckin(Checkin $checkin): Collection {
        $encounteredOrigin = false;
        return $checkin->trip->stopovers
            ->filter(function(Stopover $stopover) use ($checkin, &$encounteredOrigin): bool {
                if (!$encounteredOrigin) { // this assumes stopovers being ordered correctly
                    $encounteredOrigin = $stopover->departure_planned == $checkin->departure && $stopover->is($checkin->originStopover);
                    return false;
                }
                return true;
            })
            ->map(function(Stopover $stopover) {
                return [
                    'id'              => $stopover->id,
                    'name'            => $stopover->station->name,
                    'arrival_planned' => userTime($stopover->arrival_planned ?? $stopover->departure_planned),
                ];
            });
    }
}
