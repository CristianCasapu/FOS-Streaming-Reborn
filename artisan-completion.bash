#!/usr/bin/env bash
# Bash completion for FOS-Streaming Artisan CLI
#
# Installation:
#   1. Copy this file to: /etc/bash_completion.d/artisan
#   2. Or source it in your ~/.bashrc:
#      source /path/to/artisan-completion.bash
#   3. Reload your shell: source ~/.bashrc
#
# Usage:
#   php artisan [TAB]     - Show all commands
#   php artisan mig[TAB]  - Complete "migrate"
#   php artisan migrate:f[TAB] - Complete "migrate:fresh"

_artisan_complete()
{
    local cur prev opts base

    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"

    # All available commands
    opts="
        list
        help
        serve
        migrate
        migrate:fresh
        migrate:status
        migrate:rollback
        db:seed
        db:wipe
        sail:install
        sail:publish
    "

    # Common options
    common_opts="--help -h --version -v --quiet -q --no-interaction -n"

    # Command-specific options
    case "${prev}" in
        migrate)
            opts="--force -f --pretend -p ${common_opts}"
            ;;
        migrate:fresh)
            opts="--seed -s --force -f ${common_opts}"
            ;;
        migrate:status)
            opts="${common_opts}"
            ;;
        db:seed)
            opts="--force -f ${common_opts}"
            # Add seeder class names
            if [ -d "database/seeders" ]; then
                local seeders=$(ls database/seeders/*.php 2>/dev/null | xargs -n1 basename | sed 's/.php$//')
                opts="${opts} ${seeders}"
            fi
            ;;
        db:wipe)
            opts="--force -f ${common_opts}"
            ;;
        serve)
            opts="--host --port --tries ${common_opts}"
            ;;
    esac

    # Complete based on current word
    if [[ ${cur} == -* ]] ; then
        # Complete options
        COMPREPLY=( $(compgen -W "${common_opts}" -- ${cur}) )
        return 0
    elif [[ ${prev} == "artisan" ]] || [[ ${prev} == "php" ]]; then
        # Complete commands when after 'artisan' or 'php'
        COMPREPLY=( $(compgen -W "${opts}" -- ${cur}) )
        return 0
    fi

    return 0
}

# Register completion for 'php artisan' and './artisan'
complete -F _artisan_complete artisan
complete -F _artisan_complete ./artisan

# Also register for when 'php' precedes 'artisan'
_php_artisan_complete()
{
    local cur prev
    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"

    # If previous word is 'php' and current doesn't start with '-'
    if [[ ${prev} == "php" ]] && [[ ${cur} != -* ]]; then
        COMPREPLY=( $(compgen -W "artisan" -- ${cur}) )
        return 0
    fi

    # If we're past 'php artisan', use normal artisan completion
    local i
    for ((i=0; i < ${#COMP_WORDS[@]}; i++)); do
        if [[ ${COMP_WORDS[i]} == "artisan" ]]; then
            _artisan_complete
            return 0
        fi
    done
}

complete -F _php_artisan_complete php
