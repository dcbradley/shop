#!/usr/bin/env python3

# BSD 3-Clause License

# Copyright (c) 2023, Chad Seys <cwseys@physics.wisc.edu>

# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:

# 1. Redistributions of source code must retain the above copyright notice, this
#    list of conditions and the following disclaimer.

# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.

# 3. Neither the name of the copyright holder nor the names of its
#    contributors may be used to endorse or promote products derived from
#    this software without specific prior written permission.

# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
# AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
# SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
# CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
# OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
# OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

import socket
import subprocess
import select
import sys

# This script may be useful for managing a browser in a kiosk.  The
# web app running in the browser can connect to localhost port 9999
# when it wants the browser to be restarted, for example when the
# user logs out.

if sys.argv[1:] != []:
	cmd = sys.argv[1:]
else:
	cmd = 'chromium --no-first-run --noerrdialogs --start-maximized https://physics.wisc.edu --incognito'
	cmd = cmd.split()


def eprint(*args, **kwargs):
	print(*args, file=sys.stderr, **kwargs)

try:
	port = 9999
	host = 'localhost'
	service = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
	service.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
	service.bind((host, port))
	service.listen(1)

	eprint("listening on port " + str(port))

	popenobj = None
	while True:
		if popenobj is None or popenobj.poll() is not None:
			popenobj = subprocess.Popen(cmd)
			#popenobj = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, stdin=subprocess.DEVNULL)
			eprint("child pid " + str(popenobj.pid))

		is_readable = [service]
		is_writable = []
		is_error = []

		r, w, e = select.select(is_readable, is_writable, is_error, 1.0)
		if r:
			channel, info = service.accept()
			eprint ("connection from" + str(info))
			eprint("here terminate the browser")
			popenobj.terminate()
			popenobj = None # not needed, but causes quicker restart
		#else:
			#eprint("still waiting")
except KeyboardInterrupt:
	service.close()
	sys.exit()
