<?php
/**
 * Approve / Reject dialog for a pending ticket.
 * Rendered inside the staff $.dialog() popup.
 *
 * Available: $ticket (Ticket), $rec (array row), $errors (array)
 */
if (!defined('INCLUDE_DIR')) die('Access Denied');
$reason = Format::htmlchars($_POST['reason'] ?? '');
$decision = $_POST['decision'] ?? 'approve';
?>
<h3 class="drag-handle"><?php echo sprintf(__('Approve ticket #%s'),
    Format::htmlchars($ticket->getNumber())); ?></h3>
<a class="close" href=""><i class="icon-remove-circle"></i></a>
<hr/>
<?php if ($errors) { ?>
<div id="msg_error"><?php echo Format::htmlchars(implode(' ', $errors)); ?></div>
<?php } ?>
<form method="post" action="#p-approvals/<?php echo $ticket->getId(); ?>/decide">
    <?php echo csrf_token(); ?>
    <p><?php echo sprintf(__('Help Topic: %s'),
        '<strong>' . Format::htmlchars(Topic::getLocalNameById($rec['topic_id'])) . '</strong>'); ?></p>
    <table class="form_table" width="100%" cellpadding="2" cellspacing="0" border="0">
        <tbody>
            <tr>
                <td width="120"><?php echo __('Decision'); ?>:</td>
                <td>
                    <label><input type="radio" name="decision" value="approve" <?php
                        echo $decision === 'approve' ? 'checked' : ''; ?>>
                        <?php echo __('Approve'); ?> &mdash;
                        <?php echo __('move ticket to its normal department and make it visible'); ?></label><br/>
                    <label><input type="radio" name="decision" value="reject" <?php
                        echo $decision === 'reject' ? 'checked' : ''; ?>>
                        <?php echo __('Reject'); ?></label>
                </td>
            </tr>
            <tr>
                <td valign="top"><?php echo __('Reason'); ?>:</td>
                <td><textarea name="reason" rows="4" style="width:100%"
                    class="<?php echo isset($errors['reason']) ? 'error' : ''; ?>"><?php
                    echo $reason; ?></textarea>
                    <?php if (isset($errors['reason'])) { ?>
                    <div class="error"><?php echo Format::htmlchars($errors['reason']); ?></div>
                    <?php } ?>
                </td>
            </tr>
        </tbody>
    </table>
    <hr/>
    <p class="full-width">
        <span class="buttons pull-left">
            <input type="button" value="<?php echo __('Cancel'); ?>" class="close">
        </span>
        <span class="buttons pull-right">
            <input type="submit" value="<?php echo __('Submit'); ?>">
        </span>
    </p>
</form>
