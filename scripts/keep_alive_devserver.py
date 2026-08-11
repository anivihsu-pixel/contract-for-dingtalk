#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ========================================================================
# keep_alive_devserver.py — 8099 开发服务器守护进程（watchdog）
# 背景：本机 launchctl / crontab 均被系统限制（I/O error / Operation not
#       permitted），无法用 LaunchAgent 固化；本脚本以 start_new_session
#       脱离终端会话常驻，每 20 秒探测 8099，进程消失自动重启。
# 用法：python3 scripts/keep_alive_devserver.py     （前台/后台运行均可）
#       建议：nohup python3 scripts/keep_alive_devserver.py >/dev/null 2>&1 &
# 说明：Watchdog 自身也建议用脱离会话方式启动（见下方 run_watchdog）。
# ========================================================================
import os
import subprocess
import sys
import time
import urllib.request

PROJECT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PHP = '/Users/fengjian/bin/php'
HOST = '0.0.0.0'
PORT = 8099
CHECK_URL = f'http://127.0.0.1:{PORT}/login'
LOG = os.path.join(PROJECT, 'runtime', 'devserver.out')


def server_alive() -> bool:
    """探测 8099 是否可访问（3 秒超时）。"""
    try:
        with urllib.request.urlopen(CHECK_URL, timeout=3) as resp:
            return resp.status == 200
    except Exception:
        return False


def start_server():
    """以脱离会话方式启动 php think run（stdout 追加到 runtime/devserver.out）。"""
    os.makedirs(os.path.join(PROJECT, 'runtime'), exist_ok=True)
    out = open(LOG, 'a', encoding='utf-8')
    ts = time.strftime('%Y-%m-%d %H:%M:%S')
    out.write(f'\n[{ts}] watchdog: starting php think run -H {HOST} -p {PORT}\n')
    out.flush()
    proc = subprocess.Popen(
        [PHP, 'think', 'run', '-H', HOST, '-p', str(PORT)],
        cwd=PROJECT,
        stdout=out,
        stderr=subprocess.STDOUT,
        start_new_session=True,   # 关键：脱离当前会话/进程组，shell 退出不影响
    )
    return proc.pid


def main():
    down_count = 0
    while True:
        if server_alive():
            down_count = 0
        else:
            down_count += 1
            if down_count >= 2:   # 连续 2 次探测失败才重启，避免 php 启动慢时误重启
                try:
                    pid = start_server()
                    print(f'[{time.strftime("%H:%M:%S")}] server down, restarting pid={pid}')
                except Exception as e:
                    print(f'[{time.strftime("%H:%M:%S")}] restart failed: {e}')
                down_count = 0
        time.sleep(20)


def run_watchdog():
    """Watchdog 自身脱离会话运行（幂等：已有 watchdog 则退出）。"""
    import subprocess as sp
    import re
    # 查现有 watchdog 进程
    ps = sp.run(['pgrep', '-f', 'keep_alive_devserver.py'], capture_output=True, text=True)
    me = str(os.getpid())
    alive = [p for p in ps.stdout.split() if p != me]
    if alive:
        print(f'watchdog already running: pid={",".join(alive)}')
        return
    out = open(os.path.join(PROJECT, 'runtime', 'watchdog.out'), 'a', encoding='utf-8')
    sp.Popen([sys.executable, os.path.abspath(__file__)], cwd=PROJECT,
             stdout=out, stderr=subprocess.STDOUT, start_new_session=True)
    print('watchdog detached, running in background')


if __name__ == '__main__':
    if len(sys.argv) > 1 and sys.argv[1] == '--daemon':
        run_watchdog()
    else:
        main()
