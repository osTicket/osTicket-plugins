<?php
require_once INCLUDE_DIR . 'class.plugin.php';

/**
 * Configuration for the Ticket Topic Approval plugin.
 *
 * The form is built dynamically: one multi-select per Help Topic listing every
 * agent and every team. A topic with at least one selected approver becomes an
 * "approval topic". Approver options are encoded as:
 *   s<staff_id>   -> individual agent
 *   t<team_id>    -> whole team
 */
class TopicApprovalConfig extends PluginConfig {

    // Prefix for the per-topic approver fields.
    const TOPIC_FIELD_PREFIX = 'approvers-';

    function getOptions() {
        // getOptions() runs on every request while the plugin instance is
        // bootstrapped, which is *before* $cfg is initialized. Staff/Dept/Topic
        // helpers dereference $cfg, so only build the dynamic choice lists once
        // the app is far enough along to render/save the config form.
        global $cfg;
        $ready = ($cfg instanceof Config);

        $depts = array('' => '— ' . __('Select a department') . ' —');
        $approverChoices = array();
        $topics = array();
        if ($ready) {
            try {
                foreach (Dept::getDepartments() as $id => $name)
                    $depts[$id] = $name;
                foreach (Staff::getStaffMembers() as $sid => $sname)
                    $approverChoices['s' . $sid] = sprintf('%s: %s', __('Agent'), $sname);
                foreach (Team::getTeams() as $tid => $tname)
                    $approverChoices['t' . $tid] = sprintf('%s: %s', __('Team'), $tname);
                $topics = Topic::getHelpTopics(false, true);
            } catch (Throwable $e) {
                error_log('[p-approvals] getOptions: ' . $e->getMessage());
            }
        }

        $options = array(
            'general' => new SectionBreakField(array(
                'label' => __('Holding department'),
                'hint'  => __('Pending tickets are moved here. Give this department NO '
                    . 'members other than the approvers (and make sure it is not any '
                    . 'other agent\'s primary department and is not granted to groups), '
                    . 'so unapproved tickets stay hidden from everyone else.'),
            )),
            'holding-dept' => new ChoiceField(array(
                'label'   => __('Pending Approval department'),
                'choices' => $depts,
                'default' => '',
                'hint'    => __('Required for the workflow to run.'),
            )),
            'behaviour' => new SectionBreakField(array(
                'label' => __('Behaviour'),
            )),
            'notify-approvers' => new BooleanField(array(
                'label'   => __('Email the approvers when a ticket needs approval'),
                'default' => true,
                'hint'    => __('Sends a direct alert to every approver agent and '
                    . 'approver-team member for that topic.'),
            )),
            'notify-requester' => new BooleanField(array(
                'label'   => __('Email the requester when the ticket is approved or rejected'),
                'default' => true,
            )),
            'reject-requires-reason' => new BooleanField(array(
                'label'   => __('Require a reason when rejecting'),
                'default' => true,
            )),
            'close-on-reject' => new BooleanField(array(
                'label'   => __('Close the ticket when rejected'),
                'default' => true,
                'hint'    => __('If unchecked, a rejected ticket stays open in the holding '
                    . 'department with a note.'),
            )),
            'topics' => new SectionBreakField(array(
                'label' => __('Approval topics'),
                'hint'  => __('Pick the approvers for each Help Topic that must be approved. '
                    . 'Leave a topic empty to exclude it from the workflow.'),
            )),
        );

        foreach ($topics as $tid => $tname) {
            $options[self::TOPIC_FIELD_PREFIX . $tid] = new ChoiceField(array(
                'label'         => $tname,
                'choices'       => $approverChoices,
                'default'       => array(),
                'configuration' => array('multiselect' => true),
            ));
        }

        return $options;
    }

    function pre_save(&$config, &$errors) {
        // A holding department is mandatory as soon as any topic has approvers.
        $hasApprovalTopic = false;
        foreach ($config as $key => $val) {
            if (strpos($key, self::TOPIC_FIELD_PREFIX) === 0 && $val) {
                $hasApprovalTopic = true;
                break;
            }
        }
        if ($hasApprovalTopic && empty($config['holding-dept'])) {
            $errors['holding-dept'] = __('Select the Pending Approval department.');
            $errors['err'] = __('A holding department is required when an approval topic is configured.');
            return false;
        }
        return true;
    }
}
?>
