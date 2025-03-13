<?php

class TEA_ManageAttachments
extends ThreadEntryAction {
    static $id = 'manage_attachments';
    static $name = /* trans */ 'Attachments ...';
    static $icon = 'paperclip';

    function isVisible() {
        // Only thread entries with attachments should have this option
        return count($this->entry->attachments);
    }

    function isEnabled() {
        global $thisstaff;

        // Only an administrator with access to the thread, or the owner of the
        // thread item can perform this action
        $T = $this->entry->getThread()->getObject();
        return $T->checkStaffPerm($thisstaff)
            && ($thisstaff->isAdmin()
            || $thisstaff->staff_id == $this->entry->staff_id
            );
    }

    function getJsStub() {
        return sprintf(<<<JS
var url = '%s';
$.dialog(url, [201], function(xhr, resp) {
  var json = JSON.parse(resp);
  if (!json || !json.thread_id)
    return;
  $('#thread-entry-'+json.thread_id)
    .html(json.entry)
    .find('.thread-body')
    .delay(500)
    .effect('highlight');
});
JS
        , $this->getAjaxUrl());
    }

    function trigger() {
        switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            return $this->trigger__get();
        case 'POST':
            return $this->trigger__post();
        }
    }

    protected function getNewAttachmentsForm() {
        if (!isset($this->__form)) {
            $this->__form = new SimpleForm(array(
                'new_uploads' => new FileUploadField(array(
                    'id' => 'attach',
                    'name'=>'attach:thread-entry',
                )),
            ), $_POST);
        }
        return $this->__form;
    }

    protected function trigger__get($errors=array()) {
        global $cfg, $thisstaff;

        $poster = $this->entry->getStaff();
        $action = str_replace('ajax.php/','#', $this->getAjaxUrl());
        $new_attachments = $this->getNewAttachmentsForm();

        include 'templates/thread-entry-attachments.tmpl.php';
    }

    protected function trigger__post() {
        global $thisstaff;

        // Sanity check first
        $errors = array('attachment' => array());
        $attachments = array();
        foreach ($_POST['attachment-id'] as $i=>$id) {
            if (isset($_POST['attachment-keep'][$id])) {
                if (!($name = trim($_POST['attachment-name'][$id]))) {
                    $errors['attachment'][$id]
                        = __('File name is required');
                }
                else {
                    $attachments[$id] = trim(Format::striptags(
                        $_POST['attachment-name'][$id]
                    ));
                }
            }
        }

        // Add new attachments
        $new_attachments = $this->getNewAttachmentsForm();
        $clean = $new_attachments->getField('new_uploads')->getClean();
        if ($clean) {
            // XXX: Arrgh. ThreadEntry::normalizeFileInfo is protected...
            $files = array();
            foreach ($clean as $name=>$id) {
                $file = AttachmentFile::lookup($id);
                $files[] = array(
                    'id' => $id,
                    'key' => $file->key,
                    // ThreadEntry::createAttachment checks if name differs
                    'name' => $name,
                    'file' => $file,
                    'inline' => false,
                );
            }
                    
            if ($new = $this->entry->createAttachments($files)) {
                // Keep these new ones
                foreach ($new as $attach)
                    $attachments[$attach->id] = $attach->getFilename();
            }
            else {
                $errors['attachment'][0] = true;
                Messages::error(__('Unable to save new attachments'));
            }
        }

        if ($errors['attachment']) {
            return $this->trigger__get($errors);
        }

        foreach ($this->entry->attachments as $attach) {
            $id = $attach->id;
            if (!isset($attachments[$id])) {
                $attach->delete();
            }
            elseif ($attach->getFilename() != $attachments[$id]) {
                // If the file was renamed to be the same as the original file
                // name, just remove the edited name
                if ($attach->file->getName() == $attachments[$id]) {
                    $attach->name = null;
                }
                else {
                    $attach->name = $attachments[$id];
                }
                $attach->save();
            }
        }

        // Re-render the thread entry with the new attachment info
        $entry = $this->entry;
        ob_start();
        include STAFFINC_DIR . 'templates/thread-entry.tmpl.php';
        $content = ob_get_clean();
        
        Http::response('201', JsonDataEncoder::encode(array(
            'thread_id' => $this->entry->id,
            'entry' => $content,
        )));
    }
}

class TEA_AttachmentsPlugin
extends Plugin {
    function bootstrap() {
        ThreadEntry::registerAction(/* trans */ 'Manage', 'TEA_ManageAttachments');
    }
}
