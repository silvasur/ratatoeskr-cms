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
});