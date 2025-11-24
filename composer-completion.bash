#!/usr/bin/env bash
# Bash completion for Composer with FOS-Streaming custom scripts
#
# Installation:
#   1. Copy this file to: /etc/bash_completion.d/composer-fos
#   2. Or source it in your ~/.bashrc:
#      source /path/to/composer-completion.bash
#   3. Reload your shell: source ~/.bashrc
#
# Usage:
#   composer [TAB]           - Show all commands
#   composer te[TAB]         - Complete "test"
#   composer migrate:[TAB]   - Complete "migrate:fresh" or "migrate:status"

_composer_fos_complete()
{
    local cur prev opts base

    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"

    # Standard Composer commands
    standard_commands="
        about
        archive
        browse
        check-platform-reqs
        clear-cache
        clearcache
        config
        create-project
        depends
        diagnose
        dump-autoload
        dumpautoload
        exec
        fund
        global
        help
        home
        info
        init
        install
        licenses
        list
        outdated
        prohibits
        reinstall
        remove
        require
        run
        run-script
        search
        self-update
        selfupdate
        show
        status
        suggests
        update
        upgrade
        validate
        why
        why-not
    "

    # FOS-Streaming custom scripts
    custom_scripts="
        test
        test:unit
        test:feature
        test:coverage
        lint
        lint:test
        format
        analyze
        check
        security-check
        organize-docs
        serve
        serve:dev
        fresh
        migrate
        migrate:fresh
        migrate:status
        db:seed
        db:wipe
        clear-cache
        cache:clear
        optimize
        ide-helper
        check-syntax
        phpcs
        phpcs:fix
        deploy:check
        dev
        prod:deploy
    "

    # All commands combined
    all_commands="${standard_commands} ${custom_scripts}"

    # Common options
    common_opts="
        --help -h
        --quiet -q
        --verbose -v -vv -vvv
        --version -V
        --ansi
        --no-ansi
        --no-interaction -n
        --profile
        --no-plugins
        --no-scripts
        --no-cache
        --working-dir -d
    "

    # Command-specific options
    case "${prev}" in
        install)
            opts="--prefer-source --prefer-dist --dry-run --dev --no-dev --no-autoloader --no-scripts --no-progress --optimize-autoloader -o --classmap-authoritative --apcu-autoloader --ignore-platform-reqs ${common_opts}"
            ;;
        update|upgrade)
            opts="--prefer-source --prefer-dist --dry-run --dev --no-dev --lock --no-autoloader --no-scripts --no-progress --with-dependencies --optimize-autoloader -o --classmap-authoritative --apcu-autoloader --ignore-platform-reqs --prefer-stable --prefer-lowest ${common_opts}"
            ;;
        require)
            opts="--dev --dry-run --prefer-source --prefer-dist --no-progress --no-update --update-no-dev --update-with-dependencies --ignore-platform-reqs --prefer-stable --prefer-lowest --sort-packages ${common_opts}"
            ;;
        remove)
            opts="--dev --dry-run --no-progress --no-update --update-no-dev --update-with-dependencies --ignore-platform-reqs ${common_opts}"
            ;;
        dump-autoload|dumpautoload)
            opts="--optimize -o --classmap-authoritative --apcu --no-dev ${common_opts}"
            ;;
        run|run-script)
            opts="--timeout --dev --no-dev --list ${common_opts}"
            # Add custom scripts as options
            COMPREPLY=( $(compgen -W "${custom_scripts} ${common_opts}" -- ${cur}) )
            return 0
            ;;
        show)
            opts="--all --installed --platform --available --self --name-only --path --tree --latest --outdated --minor-only --direct --strict --format ${common_opts}"
            ;;
        search)
            opts="--only-name --type ${common_opts}"
            ;;
        outdated)
            opts="--all --direct --strict --minor-only --format ${common_opts}"
            ;;
        depends|why)
            opts="--recursive --tree ${common_opts}"
            ;;
        validate)
            opts="--no-check-all --no-check-lock --no-check-publish --with-dependencies --strict ${common_opts}"
            ;;
        create-project)
            opts="--stability -s --prefer-source --prefer-dist --repository --dev --no-dev --no-scripts --no-progress --no-secure-http --keep-vcs --remove-vcs --no-install --ignore-platform-reqs ${common_opts}"
            ;;
        test|test:unit|test:feature|test:coverage)
            opts="--filter --testsuite --group --exclude-group --coverage-html --coverage-clover ${common_opts}"
            ;;
        migrate|migrate:fresh|migrate:status)
            opts="--force --seed ${common_opts}"
            ;;
        db:seed)
            opts="--force --class ${common_opts}"
            ;;
        serve|serve:dev)
            opts="--host --port --tries ${common_opts}"
            ;;
        lint|format)
            opts="--test --dirty --preset --config ${common_opts}"
            ;;
        analyze)
            opts="--level --memory-limit --configuration ${common_opts}"
            ;;
    esac

    # If we're completing after 'composer' command
    if [[ ${prev} == "composer" ]] || [[ ${prev} == "run" ]] || [[ ${prev} == "run-script" ]]; then
        if [[ ${cur} == -* ]] ; then
            # Complete options
            COMPREPLY=( $(compgen -W "${common_opts}" -- ${cur}) )
        else
            # Complete commands
            COMPREPLY=( $(compgen -W "${all_commands}" -- ${cur}) )
        fi
        return 0
    fi

    # Complete options for current command
    if [[ ${cur} == -* ]] ; then
        COMPREPLY=( $(compgen -W "${opts}" -- ${cur}) )
        return 0
    fi

    return 0
}

# Register completion
complete -F _composer_fos_complete composer
complete -F _composer_fos_complete composer.phar

# Also handle 'php composer.phar'
_php_composer_complete()
{
    local cur prev
    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"

    # If previous word is 'php' and current doesn't start with '-'
    if [[ ${prev} == "php" ]] && [[ ${cur} != -* ]]; then
        COMPREPLY=( $(compgen -W "composer.phar composer" -- ${cur}) )
        return 0
    fi

    # If we're past 'php composer.phar', use normal composer completion
    local i
    for ((i=0; i < ${#COMP_WORDS[@]}; i++)); do
        if [[ ${COMP_WORDS[i]} == "composer.phar" ]] || [[ ${COMP_WORDS[i]} == "composer" ]]; then
            _composer_fos_complete
            return 0
        fi
    done
}

complete -F _php_composer_complete php
