<?php
/*
 -------------------------------------------------------------------------
 MyDashboard plugin for GLPI
 Copyright (C) 2015 by the MyDashboard Development Team.
 -------------------------------------------------------------------------

 LICENSE

 This file is part of MyDashboard.

 MyDashboard is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 MyDashboard is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with MyDashboard. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/**
 * This class extends GLPI class planning to add the functions to display a widget on Dashboard
 */
class PluginMydashboardPlanning {

   // Should return the localized name of the type
   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {

      return __('Dashboard', 'mydashboard');
   }

   /**
    * @return bool
    */
   static function canCreate() {
      return Session::haveRightsOr('plugin_mydashboard', [CREATE, UPDATE]);
   }

   /**
    * @return bool
    */
   static function canView() {
      return Session::haveRight('plugin_mydashboard', READ);
   }


   /**
    * @return array
    */
   function getWidgetsForItem() {

      if (Session::haveRight(Planning::$rightname, Planning::READMY)) {

         $icons = PluginMydashboardHelper::icons();

         return [
            PluginMydashboardMenu::$TICKET_TECHVIEW =>
               [
                  "planningwidget" => $icons["calendar"]. "&nbsp;" . __('Your planning'),
               ]
         ];
      }
      return [];
   }

   /**
    * @param $widgetId
    *
    * @return Nothing
    */
   function getWidgetContentForItem($widgetId) {
      switch ($widgetId) {
         case "planningwidget":
            if (Session::haveRight(Planning::$rightname, Planning::READMY)) {
               return self::showCentral(Session::getLoginUserID());
            }
            break;
      }
   }

   /**
    * Show the planning for the central page of a user
    *
    * @param $who ID of the user
    *
    * @return \PluginMydashboardDatatable (display function)
    */
   static function showCentral($who) {
      global $CFG_GLPI;

      if (!Session::haveRight(Planning::$rightname, Planning::READMY)
          || ($who <= 0)
      ) {
         return false;
      }

      $widget = new PluginMydashboardHtml();
      $title  = __("Your planning");
      $widget->setWidgetTitle($title);

      echo Html::css('lib/jqueryplugins/fullcalendar/fullcalendar.css',
                     ['media' => '']);
      echo Html::css('/lib/jqueryplugins/fullcalendar/fullcalendar.print.css',
                     ['media' => 'print']);
      Html::requireJs('fullcalendar');

      $when = strftime("%Y-%m-%d");

      //Get begin and duration
      $date   = explode("-", $when);
      $time   = mktime(0, 0, 0, $date[1], $date[2], $date[0]);
      $begin  = $time - 12 * MONTH_TIMESTAMP;
      $end    = $begin + 13 * MONTH_TIMESTAMP;
      $begin  = date("Y-m-d H:i:s", $begin);
      $end    = date("Y-m-d H:i:s", $end);
      $params = ['who'       => $who,
                 'who_group' => 0,
                 'whogroup'  => 0,
                 'begin'     => $begin,
                 'end'       => $end];
      $interv = [];
      foreach ($CFG_GLPI['planning_types'] as $itemtype) {
         $interv = array_merge($interv, $itemtype::populatePlanning($params));
      }
      ksort($interv);
      $events = [];

      if (count($interv) > 0) {
         foreach ($interv as $key => $val) {
            if ($val["begin"] < $begin) {
               $val["begin"] = $begin;
            }
            if ($val["end"] > $end) {
               $val["end"] = $end;
            }
            $title = $val['name'];
            if (isset($val['entities_name'])) {
               $title = $val['entities_name'] . " > " . $val['name'];
            }
            $events[] = ['title'   => $title,
                         'tooltip' => isset($val['content']) ? Html::clean($val['content']) : "",
                         'start'   => $val["begin"],
                         'end'     => $val["end"],
                         'url'     => isset($val['url']) ? $val['url'] : "",
                         'ajaxurl' => isset($val['ajaxurl']) ? $val['ajaxurl'] : "",
            ];
         }
      }
      $events    = json_encode($events);
      $list_day  = __('List by day', 'mydashboard');
      $list_week = __('List by week', 'mydashboard');
      $today     = date("Y-m-d");
      $graph     = "<script>
            $(document).ready(function() {
                $('#calendar').fullCalendar({
                  height:      400,
                  theme:       true,
                  header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'listDay,listWeek,month'
                  },
                  views: {
                    listDay: { buttonText: '$list_day' },
                    listWeek: { buttonText: '$list_week' }
                  },

                  defaultView: 'listWeek',
                  defaultDate: '$today',
                  navLinks: true, // can click day/week names to navigate views
                  editable: false,
                  eventLimit: true, // allow 'more' link when too many events
                  events: $events,
                  eventClick: function(event) {
                      if (event.url) {
                          window.open(event.url, '_blank');
                          return false;
                      }
                  },
                  eventRender: function(event, element) {
                       element.qtip({
                           content: event.tooltip
                       });
                   }
                });

              });
             </script>";
      $graph     .= "<div id='calendar'></div>";
      $widget->toggleWidgetRefresh();
      $widget->setWidgetHtmlContent(
         $graph
      );

      return $widget;
   }
}
