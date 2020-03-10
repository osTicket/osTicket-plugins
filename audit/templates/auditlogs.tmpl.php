<?php
if(!defined('OSTADMININC') || !$thisstaff || !$thisstaff->isAdmin()) die('Access Denied');

$qs = array();
if($_REQUEST['type'])
    $qs += array('type' => $_REQUEST['type']);
$type='D';

if ($_REQUEST['type'])
  $type=$_REQUEST['type'];

if($_REQUEST['state'])
    $qs += array('state' => $_REQUEST['state']);
$state=__('All');

if ($_REQUEST['state'])
  $state=$_REQUEST['state'];

//dates
$startTime  =($_REQUEST['startDate'] && (strlen($_REQUEST['startDate'])>=8))?strtotime($_REQUEST['startDate']):0;
$endTime    =($_REQUEST['endDate'] && (strlen($_REQUEST['endDate'])>=8))?strtotime($_REQUEST['endDate']):0;
if( ($startTime && $startTime>time()) or ($startTime>$endTime && $endTime>0)){
    $errors['err']=__('Entered date span is invalid. Selection ignored.');
    $startTime=$endTime=0;
} else {
    if($startTime)
        $qs += array('startDate' => $_REQUEST['startDate']);
    if($endTime)
        $qs += array('endDate' => $_REQUEST['endDate']);
}
$order = AuditEntry::getOrder($_REQUEST['order']);
$qs += array('order' => (($order=='DESC') ? 'ASC' : 'DESC'));
$qstr = '&amp;'. Http::build_query($qs);

$args = array();
parse_str($_SERVER['QUERY_STRING'], $args);
unset($args['p'], $args['_pjax']);

// Apply pagination
$events = AuditEntry::getTableInfo('AuditEntry');
$total = count($events);
$qwhere = AuditEntry::getQwhere('AuditEntry');
$pageNav=AuditEntry::getPageNav($qwhere);
$pageNav->setURL('audits.php', $args);
?>

<div id="basic_search">
    <div style="height:25px">
        <div id='filter' >
            <form action="audits.php" method="get">
                <div style="padding-left:2px;">
                    <i class="help-tip icon-question-sign" href="#date_span"></i>
                    <?php echo __('Between'); ?>:
                    <input class="dp" id="sd" size=15 name="startDate" value="<?php echo Format::htmlchars($_REQUEST['startDate']); ?>" autocomplete=OFF>
                    &nbsp;&nbsp;
                    <input class="dp" id="ed" size=15 name="endDate" value="<?php echo Format::htmlchars($_REQUEST['endDate']); ?>" autocomplete=OFF>
                    &nbsp;<?php echo __('Type'); ?>:&nbsp;<i class="help-tip icon-question-sign" href="#type"></i>
                    <select name='type'>
                        <?php
                        foreach (AuditEntry::getTypes() as $abbrev => $info) {
                            $name = AuditEntry::getObjectName($info[0]);
                            ?>
                            <option value="<?php echo $abbrev; ?>"
                            <?php echo ($type==$abbrev)?'selected="selected"':''; ?>>
                            <?php echo __($name); ?>
                          </option>
                        <?php  } ?>
                        ?>
                    </select>
                    &nbsp;<?php echo __('Events'); ?>:&nbsp;<i class="help-tip icon-question-sign" href="#events"></i>
                    <select name='state'>
                        <?php
                        foreach (Event::getStates(true) as $title) {
                            ?>
                            <option value="<?php echo $title; ?>"
                              <?php echo ($state==$title)?'selected="selected"':''; ?>>
                              <?php echo __($title); ?>
                            </option>
                        <?php  } ?>
                        ?>
                    </select>
                    &nbsp;&nbsp;
                    <input type="submit" Value="<?php echo __('Go!');?>" />
                </div>
            </form>
        </div>
    </div>
</div>
<div class="error"><?php echo $errors['err']; ?></div>
<div class="clear"></div>
<form action="audits.php" method="POST" name="audits">
    <div style="margin-bottom:20px; padding-top:5px;">
        <div class="sticky bar opaque">
            <div class="content">
                <div class="pull-left flush-left">
                    <h2><?php echo __('Audit Logs');?>
            <i class="help-tip icon-question-sign" href="#audit_logs"></i>
            </h2>
                </div>
            </div>
        </div>
    </div>
<?php csrf_token(); ?>
 <div class="pull-right" style="margin-top:5px;">
    <?php
     if ($total)
         echo '<strong>'.$pageNav->showing().'</strong>';
     else
        echo __('No audits found');
    ?>
 </div>

 <table class="list" id="dashboard-audit" style="width:100%">
     <thead>
     <tr>
         <th>Description</th>
         <th><a <?php echo $timestamp_sort; ?> href="audits.php?<?php echo $qstr; ?>&sort=timestamp"><?php echo __('Timestamp');?></a></th>
         <th>IP Address</th>
     </tr>
     </thead>
     <tbody>
         <?php
              foreach ($events as $data) { ?>
                <tr audit-id="<?php echo $data['id']; ?>">
                    <td><?php echo $data['description']; ?></td>
                    <td><?php echo $data['timestamp']; ?></td>
                    <td><?php echo $data['ip']; ?></td>
                </tr>
              <?php
              }
              ?>
       </tbody>
       <?php if (!$total){ ?>
       <tfoot>
        <tr>
           <td colspan="6">
               <?php echo __('No Audits found'); ?>
           </td>
        </tr>
       </tfoot>
     <?php }?>
   </table>
<?php
echo '<div>';
if ($total) { //Show options..
    echo '&nbsp;'.__('Page').':'.$pageNav->getPageLinks().'&nbsp;';
}
echo sprintf('<a href="#audit/export/%s/%s" id="%s" class="no-pjax nomodalexport">%s</a>',
    $type,
    $state,
    'audit-export',
    __('Export'));
?>
</form>
