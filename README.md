<h1 align="center">Scheduled Content Dashboard Pro</h1>

<p align="center">
<a href="https://wordpress.org/plugins/scheduled-content-dashboard/"><img src="https://img.shields.io/badge/DOCS-wordpress.org-F5A623?style=for-the-badge" alt="Docs"></a>
<a href="LICENSE"><img src="https://img.shields.io/badge/LICENSE-GPL--2.0-5C9E31?style=for-the-badge" alt="License"></a>
<a href="https://jeangalea.com/"><img src="https://img.shields.io/badge/BUILT%20BY-JEAN%20GALEA-8A2BE2?style=for-the-badge" alt="Built by"></a>
</p>

**A drag-and-drop Content Board for Scheduled Content Dashboard.**

## What it adds

A `Scheduled > Board` screen with one column per pipeline status the free plugin has enabled, plus a Published column showing the last 14 days.

- Drag a card to another column to change its status. Every move can be undone from the toast.
- Dropping onto Published asks for confirmation first, then publishes.
- Dropping onto Scheduled when the post's date has passed pushes the date an hour out, so it schedules instead of publishing.
- Keyboard: focus a card and use the left and right arrows to move it between columns.
- Filter by post type and author. Post types and statuses come from the free plugin's settings, so there's nothing new to configure.

Every move checks `edit_post` on the post. Moving into Scheduled or Published also requires the post type's `publish_posts` capability, and users without `edit_others_posts` only see their own content.

The `scdp_board_columns` filter changes the board's columns, as `status => label`.

## Requirements

- WordPress 5.0 or newer, PHP 7.4 or newer
- [Scheduled Content Dashboard](https://wordpress.org/plugins/scheduled-content-dashboard/) 3.1.0 or newer, active

## Install

Download `scheduled-content-dashboard-pro.zip` from the Releases page and upload it under Plugins > Add New. Updates then arrive through the WordPress updates screen.

It's free during early access.
