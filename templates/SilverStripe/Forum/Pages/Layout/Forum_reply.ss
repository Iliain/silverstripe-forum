<% include SilverStripe\\Forum\\ForumHeader %>
	$PostMessageForm

	<div id="PreviousPosts">
		<ul id="Posts">
			<% loop $Posts('DESC') %>
				<li class="$EvenOdd">
					<% include SilverStripe\\Forum\\SinglePost %>
				</li>
			<% end_loop %>
		</ul>
		<div class="clear"><!-- --></div>
	</div>

<% include SilverStripe\\Forum\\ForumFooter %>
