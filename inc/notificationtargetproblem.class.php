<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2010 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

// Class NotificationTarget
class NotificationTargetProblem extends NotificationTargetCommonITILObject {

   var $private_profiles = array();

   public $html_tags = array('##problem.solution.description##');

   /**
    *Get events related to tickets
   **/
   function getEvents() {
      global $LANG;

      $events = array('new'             => $LANG['problem'][8],
                      'update'          => $LANG['problem'][9],
                      'solved'          => $LANG['problem'][10],
                      'add_task'        => $LANG['job'][49],
                      'update_task'     => $LANG['job'][52],
                      'delete_task'     => $LANG['job'][53],
                      'closed'          => $LANG['problem'][11],
                      'delete'          => $LANG['problem'][12]);
      asort($events);
      return $events;
   }

   function getDatasForObject(CommonDBTM $item, $simple = false) {
      global $CFG_GLPI, $LANG;


      // Common ITIL datas
      $datas = parent::getDatasForObject($item, $simple);

      $datas["##problem.impacts"]  = $this->obj->getField('impactcontent');
      $datas["##problem.causes"]   = $this->obj->getField('causecontent');
      $datas["##problem.symptoms"] = $this->obj->getField('symptomcontent');

      // Complex mode : get tasks
      if (!$simple) {

         $restrict = "`problems_id`='".$this->obj->getField('id')."'";
         $tickets = getAllDatasFromTable('glpi_problems_tickets',$restrict);

         $datas['tickets'] = array();
         if (count($tickets)) {
            $ticket = new Ticket();
            foreach ($tickets as $data) {
               if ($ticket->getFromDB($data['tickets_id'])) {
                  $tmp = array();
                  $tmp['##ticket.id##']    = $data['tickets_id'];
                  $tmp['##ticket.date##']  = $ticket->getField('date');
                  $tmp['##ticket.title##'] = $ticket->getField('name');
                  $tmp['##ticket.url##']   = urldecode($CFG_GLPI["url_base"]."/index.php".
                                                            "?redirect=ticket_".$data['tickets_id']);
                  $tmp['##ticket.content##'] = $ticket->getField('content');

                  $datas['tickets'][] = $tmp;
               }
            }
         }

         $datas['##problem.numberoftickets##'] = 0;
         if (!empty($datas['problems'])) {
            $datas['##problem.numberoftickets##'] = count($datas['tickets']);
         }


         $restrict = "`problems_id`='".$this->obj->getField('id')."'";
         $restrict .= " ORDER BY `date` DESC, `id` ASC";

         //Task infos
         $tasks = getAllDatasFromTable('glpi_problemtasks',$restrict);

         foreach ($tasks as $task) {
            $tmp = array();
            $tmp['##task.author##']      = html_clean(getUserName($task['users_id']));
            $tmp['##task.category##']    = Dropdown::getDropdownName('glpi_taskcategories',
                                                                     $task['taskcategories_id']);
            $tmp['##task.date##']        = convDateTime($task['date']);
            $tmp['##task.description##'] = $task['content'];
            $tmp['##task.time##']        = Problem::getActionTime($task['actiontime']);
            $tmp['##task.status##'] = Planning::getState($task['state']);

            $tmp['##task.user##']   = "";
            $tmp['##task.begin##']  = "";
            $tmp['##task.end##']    = "";
            $tmp['##task.status##'] = "";
            if (!is_null($task['begin'])) {
               $tmp['##task.user##']   = html_clean(getUserName($task['users_id_tech']));
               $tmp['##task.begin##']  = convDateTime($task['begin']);
               $tmp['##task.end##']    = convDateTime($task['end']);
            }

            $datas['tasks'][] = $tmp;
         }

         $datas['##problem.numberoftasks##'] = 0;
         if (!empty($datas['tasks'])) {
            $datas['##problem.numberoftasks##'] = count($datas['tasks']);
         }

         $restrict = "`problems_id`='".$this->obj->getField('id')."'";
         $items = getAllDatasFromTable('glpi_items_problems',$restrict);

         $datas['items'] = array();
         if (count($tickets)) {
            foreach ($items as $data) {
               $item = new $data['itemtype'];
               if ($item->getFromDB($data['items_id'])) {
                  $tmp = array();
                  $tmp['##item.itemtype##']    = $item->getTypeName();
                  $tmp['##item.name##']        = $item->getField('name');
                  $tmp['##item.serial##']      = $item->getField('serial');
                  $tmp['##item.otherserial##'] = $item->getField('otherserial');
                  $tmp['##item.contact##']     = $item->getField('contact');
                  $tmp['##item.contactnum##']  = $item->getField('contactnum');
                  $tmp['##item.location##']    = '';
                  $tmp['##item.user##']        = '';
                  $tmp['##item.group##']       = '';
                  $tmp['##item.model##']       = '';

                  //Object location
                  if ($item->getField('locations_id') != NOT_AVAILABLE) {
                     $tmp['##item.location##']
                                 = Dropdown::getDropdownName('glpi_locations',
                                                               $item->getField('locations_id'));
                  }

                  //Object user
                  if ($item->getField('users_id')) {
                     $user_tmp = new User;
                     if ($user_tmp->getFromDB($item->getField('users_id'))) {
                        $tmp['##item.user##'] = $user_tmp->getName();
                     }
                  }

                  //Object group
                  if ($item->getField('groups_id')) {
                     $tmp['##item.group##']
                                 = Dropdown::getDropdownName('glpi_groups',
                                                               $item->getField('groups_id'));
                  }

                  $modeltable = getSingular($item->getTable())."models";
                  $modelfield = getForeignKeyFieldForTable($modeltable);

                  if ($item->isField($modelfield)) {
                     $tmp['##item.model##'] = $item->getField($modelfield);
                  }

                  $datas['items'][] = $tmp;
               }
            }
         }

         $datas['##problem.numberofitems##'] = 0;
         if (!empty($datas['items'])) {
            $datas['##problem.numberofitems##'] = count($datas['items']);
         }

      }


      return $datas;
   }

   function getTags() {
      global $LANG;

      parent::getTags();

      //Locales
      $tags = array('task.author'                  => $LANG['common'][37],
                    'task.isprivate'               => $LANG['common'][77],
                    'task.date'                    => $LANG['reports'][60],
                    'task.description'             => $LANG['joblist'][6],
                    'task.category'                => $LANG['common'][36],
                    'task.time'                    => $LANG['job'][20],
                    'task.user'                    => $LANG['common'][95],
                    'task.begin'                   => $LANG['search'][8],
                    'task.end'                     => $LANG['search'][9],
                    'task.status'                  => $LANG['joblist'][0],
                    'problem.numberoftasks'        => $LANG['mailing'][122],
                    'problem.numberoftickets'      => $LANG['Menu'][5]." - ".$LANG['tracking'][29],
                    'problem.impacts'              => $LANG['problem'][4],
                    'problem.causes'               => $LANG['problem'][5],
                    'problem.symtoms'              => $LANG['problem'][6],
                    'item.name'                    => $LANG['financial'][104],
                    'item.serial'                  => $LANG['common'][19],
                    'item.otherserial'             => $LANG['common'][20],
                    'item.location'                => $LANG['common'][15],
                    'item.model'                   => $LANG['common'][22],
                    'item.contact'                 => $LANG['common'][18],
                    'item.contactnumber'           => $LANG['common'][21],
                    'item.user'                    => $LANG['common'][34],
                    'item.group'                   => $LANG['common'][35],
                  );

      foreach ($tags as $tag => $label) {
         $this->addTagToList(array('tag'    => $tag,
                                   'label'  => $label,
                                   'value'  => true,
                                   'events' => NotificationTarget::TAG_FOR_ALL_EVENTS));
      }

     //Foreach global tags
     $tags = array('tasks'         => $LANG['mailing'][142],
                   'tickets'       => $LANG['Menu'][5],
                   'items'         => $LANG['common'][96],
                   );

      foreach ($tags as $tag => $label) {
         $this->addTagToList(array('tag'     => $tag,
                                   'label'   => $label,
                                   'value'   => false,
                                   'foreach' => true));
      }


      //Tags with just lang
      $tags = array('ticket.tickets'          => $LANG['Menu'][5],
                    'items'                   => $LANG['common'][96],);

      foreach ($tags as $tag => $label) {
         $this->addTagToList(array('tag'   => $tag,
                                   'label' => $label,
                                   'value' => false,
                                   'lang'  => true));
      }

      //Tags without lang
      $tags = array('ticket.id'              => $LANG['Menu'][5]." - ".$LANG['common'][2],
                    'ticket.date'            => $LANG['Menu'][5]." - ".$LANG['common'][27],
                    'ticket.url'             => $LANG['Menu'][5]." - ".$LANG['common'][94],
                    'ticket.title'           => $LANG['Menu'][5]." - ".$LANG['common'][16],
                    'ticket.content'         => $LANG['Menu'][5]." - ".$LANG['joblist'][6],
                   );

      foreach ($tags as $tag => $label) {
         $this->addTagToList(array('tag'   => $tag,
                                   'label' => $label,
                                   'value' => true,
                                   'lang'  => false));
      }
      asort($this->tag_descriptions);
   }
}
?>
