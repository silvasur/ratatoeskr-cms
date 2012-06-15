$(function()
{
	$("div.articleeditor-metabar-element h2").addClass("metabar_element_expanded").click(function()
	{
		self = $(this);
		if(self.hasClass("metabar_element_expanded"))
		{
			self.removeClass("metabar_element_expanded");
			self.addClass("metabar_element_collapsed");
			$("div.articleeditor-metabar-element-content", self.parent()).hide("fast");
		}
		else
		{
			self.removeClass("metabar_element_collapsed");
			self.addClass("metabar_element_expanded");
			$("div.articleeditor-metabar-element-content", self.parent()).show("fast");
		}
	});
	
	function filtertable(table, pairs)
	{
		$.each(pairs, function(idx, pair)
		{
			input = pair[0];
			column = pair[1];
			
			(function(column){input.keyup(function()
			{
				filterby = $(this).val().toLowerCase();
				$("tbody tr", table).each(function(i)
				{
					if($("td", this).eq(column).text().toLowerCase().indexOf(filterby) == -1)
						$(this).hide()
					else
						$(this).show();
				});
			});})(column);
		});
	}
	
	$("#articlestable").each(function(i)
	{
		filtertable(
			$("table", this),
			[
				[$("input[name=filter_urlname]", this), 1],
				[$("input[name=filter_tag]",     this), 4],
				[$("input[name=filter_section]", this), 5]
			]
		);
	});
	
	$("#commentstable").each(function(i)
	{
		filtertable($("table", this), [[$("input[name=filter_article]", this), 7]]);
	});
});