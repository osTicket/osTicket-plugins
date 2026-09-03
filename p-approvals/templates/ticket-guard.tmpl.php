<?php
/**
 * Injected on the staff ticket view while the ticket is pending topic approval.
 *
 * Available: $ticket, $rec, $isApprover (bool), $requireReason (bool), $approvers (string)
 *
 * The holding department is what actually hides the ticket from other agents;
 * this layer is the in-page workflow: it forces "internal note only", hides the
 * reply / status / edit affordances and gives approvers an Approve / Decline
 * action (stand-alone banner + wired into the note form so a comment can be
 * posted together with the decision).
 */
if (!defined('INCLUDE_DIR')) die('Access Denied');
$tid = (int) $ticket->getId();
?>
<style>
#p-approval-banner{margin:8px 0 12px;padding:10px 14px;border:1px solid #e0c000;
  border-left:4px solid #e0c000;background:#fffaf0;border-radius:3px;
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:0.95em;}
#p-approval-banner .p-msg{flex:1;min-width:220px;color:#7a5c00;}
#p-approval-banner .buttons{white-space:nowrap;}
#note .p-note-decide{display:inline;}
#note .p-note-decide .button{margin-right:6px;}
.p-decide-busy{opacity:.5;pointer-events:none;}
</style>

<div id="p-approval-banner">
    <span class="p-msg">
        <i class="icon-lock"></i>
        <?php echo __('This ticket is awaiting topic approval and is hidden from other agents.'); ?>
        <?php if ($approvers) echo ' ' . sprintf(__('Approvers: %s.'), Format::htmlchars($approvers)); ?>
    </span>
    <?php if ($isApprover) { ?>
    <span class="buttons">
        <button type="button" class="green button action-button" data-decision="approve">
            <i class="icon-ok-sign"></i> <?php echo __('Approve'); ?></button>
        <button type="button" class="red button action-button" data-decision="reject">
            <i class="icon-ban-circle"></i> <?php echo __('Decline'); ?></button>
    </span>
    <?php } else { ?>
    <span class="faded"><?php echo __('Only an approver can release this ticket.'); ?></span>
    <?php } ?>
</div>

<script>
(function boot(){
  if (!window.jQuery){ return setTimeout(boot, 50); }
  (function($){
  var TID = <?php echo $tid; ?>,
      IS_APPROVER = <?php echo $isApprover ? 'true' : 'false'; ?>,
      REQUIRE_REASON = <?php echo $requireReason ? 'true' : 'false'; ?>,
      MSG_REASON = <?php echo json_encode(__('Please add a note explaining the decision.')); ?>,
      DECIDE_URL = 'ajax.php/p-approvals/' + TID + '/decide';

  function csrf(){ return $('meta[name=csrf_token]').attr('content') || ''; }

  function noteBody(){
    var $ta = $('#internal_note');
    if (!$ta.length) return '';
    try { return $.trim($ta.redactor('code.get')); } catch(e){}
    return $.trim($ta.val() || '');
  }

  function decideButtons(){ return $('#p-approval-banner .buttons, #note .p-note-decide'); }

  function submitDecision(decision, reason){
    if (decision === 'reject' && REQUIRE_REASON && !reason){
      alert(MSG_REASON); return;
    }
    decideButtons().addClass('p-decide-busy');
    $.ajax({
      url: DECIDE_URL, type: 'POST',
      data: { decision: decision, reason: reason || '' },
      headers: { 'X-CSRFToken': csrf() },
      success: function(resp){
        try { var j = JSON.parse(resp); if (j && j.redirect){ window.location.href = j.redirect; return; } } catch(e){}
        window.location.reload();
      },
      error: function(xhr){
        decideButtons().removeClass('p-decide-busy');
        alert(xhr.responseText || 'Error');
      }
    });
  }

  $(function(){
    // --- Restrict the view to "internal note only" -----------------------
    $('#post-reply-tab').closest('li').hide();
    $('a.post-response[href="#post-reply"]').hide();
    $('span.action-button[data-dropdown="#action-dropdown-statuses"]').hide();
    $('a.action-button[href*="a=edit"]').hide();
    $('a.ticket-action[href*="/status/close/"], a.ticket-action[href*="/status/reopen/"]')
      .closest('.action-button, li').hide();
    $('#post-note-tab').trigger('click');
    $('a.post-response#post-note').trigger('click');
    $('#note select[name=note_status_id]').prop('disabled', true).closest('tr').hide();

    if (!IS_APPROVER) return;

    // --- Banner buttons -------------------------------------------------
    $('#p-approval-banner').on('click', 'button[data-decision]', function(){
      var d = $(this).data('decision'), body = noteBody();
      if (body) return submitDecision(d, body);
      // Nothing typed yet -> open the dialog with its own reason box.
      $.dialog(DECIDE_URL, [201], function(xhr, resp){
        try { var j = JSON.parse(resp); if (j && j.redirect){ window.location.href = j.redirect; return false; } } catch(e){}
      });
    });

    // --- Native buttons alongside "Post Note" --------------------------
    var $noteBtns = $('#note input[type=submit].save').closest('p');
    if ($noteBtns.length && !$noteBtns.find('.p-note-decide').length){
      $('<span class="p-note-decide">'
        + '<button type="button" class="green button action-button" data-decision="approve">'
        +   '<i class="icon-ok-sign"></i> ' + <?php echo json_encode(__('Approve with note')); ?> + '</button>'
        + '<button type="button" class="red button action-button" data-decision="reject">'
        +   '<i class="icon-ban-circle"></i> ' + <?php echo json_encode(__('Decline with note')); ?> + '</button>'
        + '</span>')
        .prependTo($noteBtns)
        .on('click', 'button[data-decision]', function(){
          submitDecision($(this).data('decision'), noteBody());
        });
    }
  });
  })(window.jQuery);
})();
</script>
