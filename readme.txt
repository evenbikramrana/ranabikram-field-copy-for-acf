=== Ranabikram Field Copy for ACF ===
Contributors: ranabikram
Tags: custom fields, acf, field group, copy, developer
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a "Copy" action to ACF fields so you can copy a field, with its sub-fields, into another field group while keeping the original in place.

== Description ==

Advanced Custom Fields lets you **move** a field to another field group, but moving removes it from where it was. There is no built-in way to **copy** a field into another group and keep the original.

Ranabikram Field Copy for ACF adds exactly that. A new **Copy** link appears in each field's action row, right next to Edit, Duplicate, Move and Delete. Click it, pick a destination field group, and the field is copied into that group while the original stays put.

The copy reuses ACF's own duplication routine, so it:

* Generates a fresh, unique field key (no clashes with the original)
* Recursively copies sub-fields, so Repeater, Group and Flexible Content fields come along intact
* Gives the copy a unique field name within the destination group, so it will not overwrite an existing field's values
* Works with both Advanced Custom Fields (free) and ACF PRO

This plugin runs entirely inside your WordPress admin and makes **no external requests** — no data leaves your site.

Note: like ACF's own Move and Duplicate, this copies the field *definition*, not the saved post values.

== Installation ==

1. Make sure Advanced Custom Fields (free or PRO) is installed and active.
2. Upload the plugin files to `/wp-content/plugins/ranabikram-field-copy-for-acf`, or install the plugin through the Plugins screen in WordPress.
3. Activate the plugin through the Plugins screen in WordPress.
4. Open any field group under **Custom Fields → Field Groups**. Each field now shows a **Copy** link in its action row.

== Frequently Asked Questions ==

= Does it work with ACF PRO? =

Yes. It works with both the free Advanced Custom Fields plugin and ACF PRO. It detects ACF at runtime, so it does not block activation if you run the PRO build.

= Does copying a field also copy its saved values? =

No. It copies the field *definition* into the target group, the same way ACF's own Move and Duplicate work. Existing post/page values are not transferred.

= Does it copy sub-fields? =

Yes. Repeater, Group and Flexible Content fields are copied together with all of their sub-fields, each getting a fresh field key.

= What happens if the destination group already has a field with the same name? =

The copy is automatically given a unique field name within that group (for example `email` becomes `email_2`) and its label is suffixed with " (copy)", so the two fields never overwrite each other's values on save. The original field is left untouched.

= Could a copied field still clash with a field in a different group? =

Uniqueness is enforced within the destination field group. If you copy a field into a group that is displayed on the *same edit screen* as another group that already uses that field name, the two can still share a meta key and overwrite each other on save — this is a property of how ACF stores values by field name, and it applies to ACF's own Move action too. If you copy a field into a group shown on the same screen as its source, review the copied field's name afterward and rename it if needed.

= Where does the Copy link appear? =

In the field's action row inside the Field Group editor, next to Edit, Duplicate, Move and Delete.

= Why does a copied field's conditional logic sometimes not work? =

If a field's conditional logic refers to sibling fields that do not exist in the destination group, those rules will have nothing to point to. Review the conditional logic on the copied field after moving it to a different group.

== Screenshots ==

1. The "Copy" link added to a field's action row.
2. Choosing a destination field group in the copy dialog.

== Changelog ==

= 1.0.0 =
* Initial release.
