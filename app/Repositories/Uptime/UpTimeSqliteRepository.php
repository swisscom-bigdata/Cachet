<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Repositories\Uptime;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpTimeSqliteRepository extends AbstractUpTimeRepository implements UpTimeInterface
{
    /**
     * @param Collection $component
     * @param $toDateEpoch
     * @param bool $fromDateEpoch
     *
     * @return mixed
     */
    public function getComponentsIncidentsAndUpdates(Collection $components)
    {
        return DB::select(
            'SELECT component_id,incidents.name, incidents.id as id,  CAST(strftime(\'%s\', max_time) AS INT) as max_time, CAST(strftime(\'%s\',incidents.occurred_at) as INT ) as min_time FROM ( SELECT incident_id, MAX(incident_updates.updated_at) as max_time FROM incident_updates JOIN incidents ON incident_id=incidents.id WHERE incident_updates.status = '.self::FIXED_UPDATE_STATUS_ID.' GROUP BY incident_id,incidents.occurred_at ) AS updates RIGHT JOIN incidents ON updates.incident_id = incidents.id WHERE component_id IN ( '.$components->implode('id', ',').' ) AND incidents.component_status IN ('.implode(',', self::DOWN_TIME_STATUSES).' )'
        );
    }
}
