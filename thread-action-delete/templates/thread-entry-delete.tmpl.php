<h3 class="drag-handle"><?php echo __("Remove Thread Entry"); ?></h3>
<b><a class="close" href="#"><i class="icon-remove-circle"></i></a></b>
<div class="clear"></div>
<hr />

<?php 
foreach (Messages::getMessages() as $M) { ?>
    <div class="<?php echo strtolower($M->getLevel()); ?>-banner"><?php
        echo (string) $M; ?></div>
<?php } ?>

<div style="margin: 1em">
    <p>
    <?php echo __(
        "Are you sure you want to remove this?"); ?>
    </p><p>
    <?php echo __(
        "Deleted data CANNOT be recovered."); ?>
    </p>
</div>

<form method="post" name="delete" id="delete"
    action="<?php echo $action; ?>">
    <hr />
    <p class="full-width">
        <span class="buttons pull-left">
            <input type="button" name="cancel" class="close"
            value="<?php echo __('Cancel'); ?>">
        </span>
        <span class="buttons pull-right">
            <input type="submit" class="red button" value="<?php
            echo $verb ?: __('Delete'); ?>">
        </span>
     </p>
</form>
</div>
<div class="clear"></div>
