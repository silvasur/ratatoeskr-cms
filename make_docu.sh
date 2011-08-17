#!/usr/bin/env bash
if ! [ -d docu ]; then mkdir docu; fi
if ! [ -d naturaldocs_dir ]; then mkdir naturaldocs_dir; fi
NaturalDocs -i . -o HTML docu -p naturaldocs_dir/
