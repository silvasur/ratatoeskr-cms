#!/usr/bin/env bash
if ! [ -d docu ]; then
	mkdir docu
fi
if ! [ -d naturaldocs_dir ]; then
	mkdir naturaldocs_dir
	echo -e "Format: 1.4\n\nTopic Type: STE Tag\n\n   Plural: STE Tags\n   Scope: Always global\n\n   Keywords:\n      stetag, stetags\n\nTopic Type: STE Variable\n\n   Plural: STE Variables\n   Scope: Always global\n\n   Keywords:\n      stevar, stevars\n      stevariable, stevariables" > naturaldocs_dir/Topics.txt
fi
NaturalDocs -i . -xi ./ratatoeskr/libs/ -o HTML docu -p naturaldocs_dir/
