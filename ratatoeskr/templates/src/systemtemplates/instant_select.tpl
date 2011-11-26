<ste:mktag name="instant_select" mandatory="name|array"><select name="$_tag_parameters[name]">
	<ste:foreach array="$_tag_parameters[array]" value="instant_select_v">
		<option value="$instant_select_v"?{~{$_tag_parameters[selected]|eq|$instant_select_v}| selected="selected"|}>$instant_select_v</option>
	</ste:foreach>
</select></ste:mktag>
