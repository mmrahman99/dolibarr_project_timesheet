<?php
/* 
 * Copyright (C) 2015 delcroip <delcroip@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */


include 'core/lib/includeMain.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once './core/lib/timesheet.lib.php';
require_once './class/TimesheetReport.class.php';
$htmlother = new FormOther($db);


$id		= GETPOST('id','int');
$action		= GETPOST('action','alpha');
$dateStart	= GETPOST('dateStart');
$exportfriendly=GETPOST('exportfriendly');
$optioncss = GETPOST('optioncss','alpha');

// Load traductions files requiredby by page
//$langs->load("companies");
$langs->load("main");
$langs->load("projects");
$langs->load('timesheet@timesheet');

//find the right week
//find the right week
$toDate                 = GETPOST('toDate');
$toDateday =(!empty($toDate) && $action=='goToDate')? GETPOST('toDateday'):0; // to not look for the date if action not goTodate
$toDatemonth                 = GETPOST('toDatemonth');
$toDateyear                 = GETPOST('toDateyear');
if($toDateday==0 && $datestart ==0 && isset($_SESSION["dateStart"])) {
    $dateStart=$_SESSION["dateStart"];
}else {
    $dateStart=parseDate($toDateday,$toDatemonth,$toDateyear,$datestart);
}

$_SESSION["dateStart"]=$dateStart ;


llxHeader('',$langs->trans('projectReport'),'');
$mode=($_POST['short']==1)?1:2;

$userid=  is_object($user)?$user->id:$user;



//querry to get the project where the user have priviledge; either project responsible or admin

$sql='SELECT pjt.rowid,pjt.ref,pjt.title,pjt.dateo,pjt.datee FROM '.MAIN_DB_PREFIX.'projet as pjt';
if(!$user->admin){    
    $sql.=' JOIN '.MAIN_DB_PREFIX.'element_contact ON pjt.rowid= element_id ';
    $sql.=' WHERE fk_c_type_contact = "160" ';
    $sql.=' AND fk_socpeople="'.$userid.'"';
}

dol_syslog('timesheet::report::projectList ', LOG_DEBUG);
//launch the sql querry

$resql=$db->query($sql);
$numProject=0;
$projectList=array();
if ($resql)
{
        $numProject = $db->num_rows($resql);
        $i = 0;
        // Loop on each record found, so each couple (project id, task id)
        while ($i < $numProject)
        {
                $error=0;
                $obj = $db->fetch_object($resql);
                $projectList[$obj->rowid]=new TimesheetReport($db);
                $projectList[$obj->rowid]->initBasic($obj->rowid,'',$obj->ref.' - '.$obj->title);
                $i++;
        }
        $db->free($resql);
}else
{
        dol_print_error($db);
}

$Form='<form action="?action=reportproject'.(($optioncss != '')?'&amp;optioncss='.$optioncss:'').'" method="POST">
        <table class="noborder"  width="100%">
        <tr>
        <td>'.$langs->trans('Project').'</td>
        <td>'.$langs->trans('Month').'</td>
        <td></td>
        </tr>
        <tr >
        <td><select  name="projectSelected">
        ';
foreach($projectList as $pjt){
    $Form.='<option value="'.$pjt->projectid.'" '.(($_POST['projectSelected']==$pjt->projectid)?"selected":'').' >'.$pjt->name.'</option>'."\n";
}
//    if($user->admin){
        $Form.='<option value="-999" '.(($_POST['projectSelected']=="-999")?"selected":'').' >'.$langs->trans('All').'</option>'."\n";
//    }
   
$mode='UTD';
$querryRes='';
if (!empty($_POST['projectSelected']) && is_numeric($_POST['projectSelected']) 
        &&!empty($_POST['month']))
{
    $mode=$_POST['mode'];
    $short=$_POST['short'];
    $projectSelected=$projectList[$_POST['projectSelected']];
    $year=$_POST['year'];
    $month=$_POST['month'];//strtotime(str_replace('/', '-',$_POST['Date'])); 
    $firstDay= strtotime('01-'.$month.'-'. $year);
    $lastDay=  strtotime('last day of this month',$firstDay);
    if($_POST['projectSelected']=='-999'){
        foreach($projectList as $project){
        $querryRes.=$project->getHTMLreport($firstDay,$lastDay,$mode,$short,
            $langs->trans(date('F',strtotime('12/13/1999 +'.$month.' month'))),
            $conf->global->TIMESHEET_DAY_DURATION,$exportfriendly);
        }
    }else{
    $querryRes=$projectSelected->getHTMLreport($firstDay,$lastDay,$mode,$short,
            $langs->trans(date('F',strtotime('12/13/1999 +'.$month.' month'))),
            $conf->global->TIMESHEET_DAY_DURATION,$exportfriendly);
    }
    
}else
{
    $year=date('Y',$dateStart);
    $month=date('m',$dateStart);
}

$Form.='</select></td>'
        .'<td> '.$htmlother->select_month($month, 'month').' - '.$htmlother->selectyear($year,'year',0,10,3)
        .' </td><td><input type="checkbox" name="short" value="1" '
        .(($short==1)?'checked>':'>').$langs->trans('short').'</td>'
        .'<td><input type="checkbox" name="exportfriendly" value="1" '
        .(($exportfriendly==1)?'checked>':'>').$langs->trans('exportfriendly').'</td>'
        . '<td><input type="radio" name="mode" value="UTD" '.($mode=='UTD'?'checked':'')
        .'> '.$langs->trans('User').' / '.$langs->trans('Task').' / '.$langs->trans('Date').'<br>'
        . '<input type="radio" name="mode" value="UDT" '.($mode=='UDT'?'checked':'')
        .'> '.$langs->trans('User').' / '.$langs->trans('Date').' / '.$langs->trans('Task').'<br>'
        . '<input type="radio" name="mode" value="DUT" '.($mode=='DUT'?'checked':'')
        .'> '.$langs->trans('Date').' / '.$langs->trans('User').' / '.$langs->trans('Task').'<br>'
        .'<td><input class="butAction" type="submit" value="'.$langs->trans('getReport').'"><br>';
if(!empty($querryRes) && ($user->rights->facture->creer || version_compare(DOL_VERSION,"3.7")<=0 ))$Form.='<br><a class="butAction" href="TimesheetProjectInvoice.php?step=0&year='.$_POST['year'].'&month='.$_POST['month'].'&projectid='.$_POST['projectSelected'].'" >'.$langs->trans('Invoice').'</a>';
        $Form.='</td></tr></table></form>';
if(!($optioncss != '' && !empty($_POST['userSelected']) )) echo $Form;




echo $querryRes;


llxFooter();
$db->close();
?>
