<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Http\Controllers;

use AltThree\Badger\Facades\Badger;
use CachetHQ\Cachet\Http\Controllers\Api\AbstractApiController;
use CachetHQ\Cachet\Models\Component;
use CachetHQ\Cachet\Models\ComponentGroup;
use CachetHQ\Cachet\Models\Incident;
use CachetHQ\Cachet\Models\Metric;
use CachetHQ\Cachet\Models\Schedule;
use CachetHQ\Cachet\Repositories\Metric\MetricRepository;
use CachetHQ\Cachet\Repositories\Uptime\UpTimeRepository;
use CachetHQ\Cachet\Services\Dates\DateFactory;
use CachetHQ\Cachet\Services\Excel\UpTimesExporter;
use Carbon\Carbon;
use Exception;
use GrahamCampbell\Binput\Facades\Binput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Jenssegers\Date\Date;
use McCool\LaravelAutoPresenter\Facades\AutoPresenter;

/**
 * This is the uptimes controller endpoint.
 *
 * @author Diogo Ferreira Venancio <diogo.ferreiravenancio@swisscom.com>
 */
class UpTimesController extends AbstractApiController {

  const LAST_DAYS = 30;
  const LAST_HOURS = 48;

  private function fetchUpTime($type, $components, $range){
      $upTimes = app(UpTimeRepository::class);
      $fromDate = Carbon::createFromFormat("Y-m-d H:i", $range["fromDate"]);
      $toDate = Carbon::createFromFormat("Y-m-d H:i", $range["toDate"]);
      $hours = 0;

      switch ($type){
          case 'last_hours':
              $fromDate->setTime($fromDate->hour,0,0);
              $toDate->setTime($toDate->hour,0,0);
              $hours = 1.0;
              break;
          case 'last_days':
              $fromDate->setTime(0,0,0);
              $toDate->setTime(0,0,0);
              $hours = 24.0;
              break;
      }

      $data = $upTimes->ComponentsUpTimeFor(
          $components,
          $fromDate,
          $toDate,
          $hours
      );

      // Reverse the arrays to have it today right and last day left
      return [
          "items" => array_reverse($data["upTimes"]),
          "labels" => array_reverse(array_keys($data["upTimes"])),
          "incidentsIds" => array_reverse($data["incidentsIds"]),
          "avaibility" => $data["avaibility"]
      ];
  }


  private function createDates($range, $type){
      $today = Carbon::now();
      $range = [];
      $range["fromDate"] = $today->format("Y-m-d H:00");
      switch ($type){
          case 'last_days':
              $range["toDate"] = $today
                  ->subDays(self::LAST_DAYS)
                  ->format("Y-m-d H:00");
              break;
          case 'last_hours':
              $range["toDate"] = $today
                  ->subHours(self::LAST_HOURS)
                  ->format("Y-m-d H:00");
              break;
      }
      return $range;
  }

  private function getDateRange($type){
    $range = Binput::get('range', NULL);
    if($range !== NULL && $range !== "" ){
        $rangeValidation = Validator::make($range, $rules = [
            'fromDate'      => 'date|date_format:Y-m-d H:00|after:toDate',
            'toDate'        => 'date|date_format:Y-m-d H:00|before:fromDate',
        ])->validate();
        return $range;
    }else {
        return $this->createDates($range, $type);
    }
  }

  /**
   * @param Component $component
   * @return \Illuminate\Http\JsonResponse
   */
  public function getUpTime(Component $component){
      $type = Binput::get('filter', 'last_hours');
      $range = $this->getDateRange($type);
      return $this->item($this->fetchUpTime($type,collect([$component]),$range));
  }


  /**
   * @param ComponentGroup $group
   * @return \Illuminate\Http\JsonResponse
   */
  public function getUpTimeByGroup(ComponentGroup $group){
      $type = Binput::get('filter', 'last_hours');
      $range = $this->getDateRange($type);
      return $this->item($this->fetchUpTime($type,$group->components()->get(),$range));
  }


  public function exportToFile(){
      $format = Binput::get('format', 'xlsx');
      $range = $this->getDateRange("last_hours");
      //Prepare data for export ...
      $data = [
          "groups" => ComponentGroup::get()->map(function($g) use ($range) {
              return [
                  "name" => $g->name,
                  "id" => $g->id,
                  "data" => $this->fetchUpTime("last_hours",$g->components()->get(), $range),
                  "components" => $g->components()->get()->map(function ($c) use ($range){
                      return [
                          "name" => $c->name,
                          "id" => $c->id,
                          "data" => $this->fetchUpTime("last_hours",collect([$c]), $range)
                      ];
                  })
              ];
          })
      ];
      UpTimesExporter::createFile(
        $data,
        $range,
        $format
      );
      return back();
  }

}
