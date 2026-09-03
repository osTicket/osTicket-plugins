# Ticket Topic Approval (`p-approvals`)

Holds tickets that are opened under a flagged Help Topic in a restricted
department so they stay **invisible to every agent except the topic's designated
approvers** until one of them approves or rejects the ticket.

* The ticket **requester always sees their ticket normally** (the hold only
  affects agents).
* **Approve** → the ticket is moved back to the department it would normally
  have landed in and becomes visible to all agents of that department.
* **Reject** → an internal note with the reason is added and (by default) the
  ticket is closed.

## How the hiding works

osTicket has no hook inside `Ticket::checkStaffPerm()`, so visibility is enforced
natively: agents only see tickets in departments they have access to. On
creation the plugin moves a pending ticket into a **holding department** whose
only members are the approvers, and records the department the ticket should
return to on approval (`ost_plugin_topic_approval`).

> Note: osTicket administrators can still see every ticket regardless of
> department. Approval is enforced for regular agents.

## Install

1. Copy this folder to `include/plugins/p-approvals` (already in place here via
   the `./plugins` bind-mount).
2. **Admin Panel → Agents → Departments → Add New Department**, e.g.
   `Pending Approval`:
   * do **not** add any members except the approvers;
   * make sure it is **not** the *Primary Department* of any other agent and is
     **not** granted through any Group's department access;
   * set *Ticket Assignment* / alerts as you like.
3. **Admin Panel → Manage → Plugins → Add New Plugin → Ticket Topic Approval →
   Install**.
4. Open the plugin, **Add New Instance**, and in the config:
   * pick the **Pending Approval department** as the holding department;
   * for every Help Topic that must be approved, select one or more
     **approvers** (agents and/or teams). Topics left empty are ignored.
   * enable the instance.

## While a ticket is pending

On the staff ticket view the plugin (via the `object.view` signal /
`templates/ticket-guard.tmpl.php`):

* shows a prominent **Approve / Decline** bar at the top;
* forces the **Internal Note** tab and hides *Post Reply*, *Change Status*,
  *reopen/close* and *Edit* – so agents can only add internal notes;
* adds **“Approve with this note” / “Decline with this note”** buttons to the
  Internal Note form, so a comment is posted together with the decision;
* keeps the *Approve / Reject Ticket* item in the ⚙ *More* menu too.

Each step is also written to the ticket **timeline as an event** (not just a
note): *Held for topic approval*, *Topic approval granted by …*, *Topic approval
rejected by …*. These are real `ThreadEvent` subclasses in `class.approval.php`;
their state names are registered in `ost_event` by `ensureSchema()`.

The decision endpoint (`ajax.php/p-approvals/<id>/decide`) always re-checks that
the caller is an approver. The in-page hiding is a workflow convenience –
real isolation comes from the holding department. Hard server-side blocking of
replies/status changes would require patching osTicket core.

## Email notifications

Toggled in the plugin config (`notify-approvers`, `notify-requester`, both on by
default) and rendered from **editable Email Templates** that the plugin registers
in every template set (**Admin Panel → Emails → Templates**):

| Code name | Section | Sent to | When |
|---|---|---|---|
| `approval.needed` | Ticket Agent Email Templates | each approver agent / team member | ticket held |
| `approval.granted` | Ticket End-User Email Templates | requester | approved |
| `approval.rejected` | Ticket End-User Email Templates | requester | rejected |

Bodies are seeded once by `TopicApproval::ensureTemplates()`; edit them freely
afterwards. Context vars: `ticket`, `recipient`.

## Files

| File | Purpose |
|---|---|
| `plugin.php` | Manifest. |
| `p-approvals.php` | `TopicApprovalPlugin` – signal wiring (`ticket.created`, `ticket.view.more`, `ajax.scp`, `object.view`). |
| `config.php` | `TopicApprovalConfig` – dynamic per-topic approver matrix + toggles. |
| `class.approval.php` | `TopicApproval` – persistence, approve/reject logic, thread events, email templates. |
| `templates/decide.tmpl.php` | Approve / Reject dialog. |
| `templates/ticket-guard.tmpl.php` | In-page workflow injected while a ticket is pending. |

## Data

Table `ost_plugin_topic_approval` (created automatically on bootstrap):
`ticket_id` (PK), `topic_id`, `status` (`pending`/`approved`/`rejected`),
`target_dept_id`, `requested_at`, `resolved_by`, `resolved_at`, `reason`.

## Limitations

* Changing a ticket's Help Topic *after* creation does not trigger the workflow.
* The holding department's access configuration is a manual admin
  responsibility (see Install step 2).
* A custom signal `ticket.approved` is emitted on approval for further
  extension.
