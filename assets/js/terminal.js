/* terminal.js — fake interactive terminal on the About page.
   All output built with textContent (no innerHTML of user input) — XSS-safe. */
(function () {
    'use strict';

    var body = document.getElementById('termBody');
    var input = document.getElementById('termInput');
    if (!body || !input) return;

    var CMDS = {
        help: function () {
            return ['Available commands:',
                '  whoami        who runs this site',
                '  ls skills     skill list',
                '  ls tools      toolbox',
                '  cat mission   why this blog exists',
                '  uname -a      site tech stack',
                '  sudo su       (try it)',
                '  clear         clear the screen'];
        },
        whoami: function () {
            return ['security enthusiast · Bangladesh 🇧🇩', 'ethical hacking / CTF / defensive hardening'];
        },
        'ls skills': function () {
            return ['web-app-security/  network-recon/  ctf-wargames/', 'linux-scripting/  osint/  reverse-engineering/'];
        },
        'ls tools': function () {
            return ['nmap  burpsuite  metasploit  wireshark', 'sqlmap  ffuf  ghidra  hashcat'];
        },
        'cat mission': function () {
            return ['Learn in public. Break things legally.', 'Write it down so others can learn too.',
                'Everything here: education & authorized testing ONLY.'];
        },
        'uname -a': function () {
            return ['CyberBlogs 1.0 byte.pro.bd PHP8/MySQL hand-rolled #OWASP-hardened', 'No frameworks. No trackers. CSP: script-src \'self\'.'];
        },
        'sudo su': function () {
            return ['[sudo] password for guest: ', 'guest is not in the sudoers file.', 'This incident will be reported. 🚨 (logged, actually)'];
        },
        clear: null // handled specially
    };

    function print(line, cls) {
        var div = document.createElement('div');
        div.className = 'term-line' + (cls ? ' ' + cls : '');
        div.textContent = line;
        body.appendChild(div);
    }

    function run(raw) {
        var cmd = raw.trim().toLowerCase().replace(/\s+/g, ' ');
        print('guest@byte:~$ ' + raw, 'term-echo');
        if (cmd === '') return;
        if (cmd === 'clear') {
            body.innerHTML = '';
            return;
        }
        var fn = CMDS[cmd];
        if (fn) {
            fn().forEach(function (l) { print(l); });
        } else {
            print('bash: ' + cmd.split(' ')[0] + ': command not found (try `help`)', 'term-err');
        }
        body.scrollTop = body.scrollHeight;
    }

    var history = [];
    var hPos = -1;
    input.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
            run(input.value);
            if (input.value.trim()) { history.unshift(input.value); }
            hPos = -1;
            input.value = '';
        } else if (ev.key === 'ArrowUp') {
            ev.preventDefault();
            if (hPos < history.length - 1) input.value = history[++hPos];
        } else if (ev.key === 'ArrowDown') {
            ev.preventDefault();
            input.value = hPos > 0 ? history[--hPos] : (hPos = -1, '');
        }
    });

    // Click anywhere in the terminal focuses the input
    document.getElementById('fakeTerminal').addEventListener('click', function () { input.focus(); });
})();
