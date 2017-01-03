<h3 class="drag-handle"><?php echo __("Thread Entry Attachments"); ?></h3>
<b><a class="close" href="#"><i class="icon-remove-circle"></i></a></b>
<div class="clear"></div>
<hr />

<div style="margin:1em"><?php echo __(
    "Uncheck attachments to remove them. Change the filename to rename the file for its attachment to this thread entry."); ?>
</div>

<?php 
foreach (Messages::getMessages() as $M) { ?>
    <div class="<?php echo strtolower($M->getLevel()); ?>-banner"><?php
        echo (string) $M; ?></div>
<?php } ?>

<form method="post" action="<?php echo $action; ?>">
    <div style="margin:1em 2em">
    <?php foreach ($this->entry->getAttachments() as $attach) {
        include 'attachment.tmpl.php';
    }
    ?>
    </div>
    
    <div style="margin:1em 1em 0">
    <?php
    echo $new_attachments->asTable();
    ?>
    </div>

    <hr />
    <p class="full-width">
        <span class="buttons pull-left">
            <input type="reset" value="<?php echo __('Reset'); ?>" />
            <input type="button" name="cancel" class="close"
            value="<?php echo __('Cancel'); ?>">
        </span>
        <span class="buttons pull-right">
            <input type="submit" class="red button" value="<?php
            echo $verb ?: __('Update'); ?>">
        </span>
     </p>
</form>
<div class="clear"></div>

