<% include SilverStripe\\Forum\\ForumHeader %>

<table class="forum-topics">

	<% if $GlobalAnnouncements %>
		<tr class="category">
			<td colspan="4"><% _t('ForumHolder_ss.ANNOUNCEMENTS', 'Announcements') %></td>
		</tr>
		<% loop $GlobalAnnouncements %>
			<% include SilverStripe\\Forum\\ForumHolder_List %>
		<% end_loop %>
	<% end_if %>

	<% if $ShowInCategories %>
		<% loop Forums %>
			<tr class="category"><td colspan="4">$Title</td></tr>
			<tr class="category">
				<th><% if Count = 1 %><% _t('ForumHolder_ss.FORUM','Forum') %><% else %><% _t('ForumHolder_ss.FORUMS', 'Forums') %><% end_if %></th>
				<th><% _t('ForumHolder_ss.THREADS','Threads') %></th>
				<th><% _t('ForumHolder_ss.POSTS','Posts') %></th>
				<th><% _t('ForumHolder_ss.LASTPOST','Last Post') %></th>
			</tr>
			<% loop $CategoryForums %>
				<% include SilverStripe\\Forum\\ForumHolder_List %>
			<% end_loop %>
		<% end_loop %>
	<% else %>
		<tr class="category">
			<td><% _t('ForumHolder_ss.FORUM','Forum') %></td>
			<td><% _t('ForumHolder_ss.THREADS','Threads') %></td>
			<td><% _t('ForumHolder_ss.POSTS','Posts') %></td>
			<td><% _t('ForumHolder_ss.LASTPOST','Last Post') %></td>
		</tr>
		<% loop $Forums %>
			<% include SilverStripe\\Forum\\ForumHolder_List %>
		<% end_loop %>
	<% end_if %>
</table>

<% include SilverStripe\\Forum\\ForumFooter %>
