<div style="margin:0.5em">
    <input type="hidden" name="attachment-id[]"
        value="<?php echo $attach->id; ?>" class="-attachment-keep" />
    <label class="checkbox">
        <input class="checkbox" type="checkbox" checked="checked"
            onchange="javascript:
            var T=$(this).closest('div');
            T.add(T.find('.-attachment-name'))
                .toggleClass('strike', !$(this).prop('checked'))"
            name="attachment-keep[<?php echo $attach->id; ?>]"
            class="-attachment-keep" />
        <input type="text" size="30" maxlength="255"
            class="-attachment-name"
            value="<?php echo Format::htmlchars($attach->getFilename()); ?>"
            name="attachment-name[<?php echo $attach->id; ?>]" />
        <?php echo Format::file_size($attach->file->size); ?>
        <a class="pull-right" href="<?php echo Format::htmlchars($attach->file->getDownloadUrl()); ?>"
            ><?php echo __('Download'); ?></a>
            
    </label>
<?php foreach (@$errors['attachment'][$attach->id] as $err) { ?>
        <div class="error"><?php echo Format::htmlchars($err); ?></div>
<?php } ?>
</div>
<div class="clear"></div>
