<?php
$events = AuditEntry::getTableInfo($ticketId,false, 'T');
$total = count($events);
$qwhere = AuditEntry::getQwhere($ticketId, false, 'T');
$pageNav=AuditEntry::getPageNav($qwhere);
$pageNav->setURL('tickets.php', $args);
$order = AuditEntry::getOrder($_REQUEST['order']);
$qs = array();
$qsReverse = array();
$qs += array('order' => $order);
$qsReverse += array('order' => ($order=='DESC' ? 'ASC' : 'DESC'));
$qstr = Http::build_query($qs);
$qstrReverse = Http::build_query($qsReverse);
$url = '#audit/ticket/' . $ticketId . '/view?';
$qstr = sprintf('%s&sort=timestamp', $qstr);
 ?>
<div id="ticket-audit-p1" style="display:block;">
<h3><?php echo __('Ticket History'); ?></h3>
<b><a class="close" href="#"><i class="icon-remove-circle"></i></a></b>
<hr/>
<div class="pull-left" style="margin-top:5px;">
   <?php
    if ($total)
        echo '<strong>'.$pageNav->showing().'</strong>';
    else
        echo sprintf(__('%s does not have any audits'), __('Ticket'));
   ?>
</div>
<div class="clear"></div>
<?php if ($total) { ?>
<div>
<table class="list" id="ticket-view-audit" style="width:100%">
    <thead>
    <tr>
        <th>Description</th>
        <th><a class="audit-sort" <?php echo $timestamp_sort;
            echo sprintf('href="#audit/ticket/%d/view?&%s"',$ticketId, $qstrReverse);
            ?>><?php echo __('Timestamp');?></a></th>
        <th>IP Address</th>
    </tr>
    </thead>
    <tbody class="audits">
<?php
foreach ($events as $data) { ?>
  <tr data-staff-id="<?php echo $ticketId; ?>">
      <td><?php echo $data['description']; ?></td>
      <td><?php echo $data['timestamp']; ?></td>
      <td><?php echo $data['ip']; ?></td>
  </tr>
<?php } ?>
    </tbody>
</table>
<hr/>
<?php
$links = $pageNav->getPageLinks('audits');
$links = str_replace('<a href', '<a class=audit-page href', $links);
$links = str_replace('tickets.php?&amp;', $url, $links);
$links = str_replace('?p', '?'.$qstr.'&p', $links);
    echo '<div>';
    echo '&nbsp;'.__('Page').':'.$links.'&nbsp;';
    echo sprintf('<a href="ajax.php/audit/export/tid,%d" id="%s" class="no-pjax nomodalexport">%s</a>',
        $ticketId,
        'audit-export',
        __('Export'));
    echo '</div>';
?>
<p class="full-width">
    <span class="buttons pull-right">
        <input type="button" name="cancel" class="close"
            value="<?php echo __('Close'); ?>">
    </span>
</p>
<?php } ?>
</div>
</div>
<div id="ticket-audit-next" style="display:none;">
</div>
<script type="text/javascript">
$(function() {
    $(document).off('click.ticket-audit');
    $(document).on('click.ticket-audit', 'tbody.audits a, a.audit-page, a.audit-sort', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        thisHref = $(this).attr('href');
        if ('<?php echo $url; ?>'.length > 1) {
            $('div#ticket-audit-next').empty();
            var url = thisHref.replace("#", "ajax.php/");
            var $container = $('div#ticket-audit-next');
            $container.load(url, function () {
                $('.tip_box').remove();
                $('div#ticket-audit-p1').hide();
                $.pjax({url: url, container: 'div#ticket-audit-next', push: false});
            }).show();
        }
        return false;
     });
     //override pjax:complete to keep showing overlay on pagination
     $(document).on('pjax:complete', function() {
         if ($('#popup').is(':visible')) {
             $.toggleOverlay(true);
             $('#overlay').show();
         }
     });
});
</script>
