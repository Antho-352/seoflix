#!/usr/bin/env python3
"""
Fetch top videos from SEO YouTube channels for the seoflix backlog.

Per channel:
  1. Fetch up to 200 videos via flat-playlist (id, title, duration, views).
  2. Filter videos shorter than MIN_DURATION (Shorts + < 7 min).
  3. Sort by view_count desc, take top N matching the channel quota.
  4. Fetch full metadata (incl. description) for selected videos.

Output:
  /tmp/seoflix-raw/<handle_no_at>.json — { handle, channel: {...}, videos: [...] }

Run:
  python3 scripts/fetch-backlog.py
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

import yt_dlp

CHANNELS: dict[str, int] = {
	'@wizardspodcast':   7,
	'@LesWizards':       3,
	'@paulvengeons':     5,
	'@MVP-podcast':      3,
	'@linkuma_off':      3,
	'@bhcfrance':        4,
	'@Ares-eo':          3,
	'@FlorentinDoreau':  2,
	'@leopoitevin':      4,
	'@GabinWeb':         3,
	'@SylvainPeyronnet': 3,
	'@Linksgarden':      3,
	'@pimptonseo':       2,
	'@jeromepasquelin':  3,
	'@victorcollas':     2,
}

MIN_DURATION = 420  # 7 minutes
RAW_DIR = Path('/tmp/seoflix-raw')


def fetch_channel_metadata(handle: str) -> dict:
	url = f'https://www.youtube.com/{handle}'
	opts = {'quiet': True, 'no_warnings': True, 'extract_flat': 'in_playlist'}
	with yt_dlp.YoutubeDL(opts) as ydl:
		info = ydl.extract_info(url, download=False)
		thumbs = info.get('thumbnails') or []
		thumb_url = None
		if thumbs:
			# Prefer the largest avatar / banner.
			thumb_url = thumbs[-1].get('url')
		return {
			'channel_id':       info.get('channel_id') or info.get('uploader_id'),
			'channel_url':      info.get('channel_url') or info.get('uploader_url'),
			'description':      (info.get('description') or '')[:500],
			'subscriber_count': info.get('channel_follower_count'),
			'title':            info.get('channel') or info.get('uploader') or info.get('title'),
			'thumbnail_url':    thumb_url,
		}


def fetch_channel_videos(handle: str, limit: int = 200) -> list[dict]:
	url = f'https://www.youtube.com/{handle}/videos'
	opts = {
		'quiet':         True,
		'no_warnings':   True,
		'extract_flat':  True,
		'playlistend':   limit,
	}
	with yt_dlp.YoutubeDL(opts) as ydl:
		info = ydl.extract_info(url, download=False)
		entries = info.get('entries') or []
		return [
			{
				'id':         e.get('id'),
				'title':      e.get('title'),
				'duration':   e.get('duration'),
				'view_count': e.get('view_count'),
			}
			for e in entries
			if e and e.get('id')
		]


def fetch_video_full(video_id: str) -> dict:
	url = f'https://www.youtube.com/watch?v={video_id}'
	opts = {'quiet': True, 'no_warnings': True, 'skip_download': True}
	with yt_dlp.YoutubeDL(opts) as ydl:
		info = ydl.extract_info(url, download=False)
		# Truncate description to keep payload reasonable.
		desc = info.get('description') or ''
		return {
			'youtube_id':       info.get('id'),
			'title':            info.get('title'),
			'description':      desc[:2000],
			'duration_seconds': info.get('duration'),
			'view_count':       info.get('view_count'),
			'upload_date':      info.get('upload_date'),  # YYYYMMDD
			'thumbnail':        info.get('thumbnail'),
			'tags':             (info.get('tags') or [])[:20],
		}


def process_channel(handle: str, quota: int) -> dict:
	print(f'\n=== {handle} (quota {quota}) ===', file=sys.stderr, flush=True)
	try:
		ch_meta = fetch_channel_metadata(handle)
	except Exception as e:
		return {'handle': handle, 'error': f'channel-meta: {e}'}

	try:
		videos = fetch_channel_videos(handle, limit=200)
	except Exception as e:
		return {'handle': handle, 'error': f'channel-videos: {e}'}

	filtered = [v for v in videos if (v.get('duration') or 0) >= MIN_DURATION]
	filtered.sort(key=lambda v: v.get('view_count') or 0, reverse=True)
	selected_lite = filtered[:quota]
	print(f'  {len(videos)} total → {len(filtered)} after duration filter → {len(selected_lite)} selected', file=sys.stderr, flush=True)

	full_videos = []
	for v in selected_lite:
		vid = v['id']
		try:
			full = fetch_video_full(vid)
			full_videos.append(full)
			print(f'  ✓ {vid} — {full["title"][:60]}', file=sys.stderr, flush=True)
		except Exception as e:
			print(f'  ✗ {vid} — {e}', file=sys.stderr, flush=True)

	output = {
		'handle':  handle,
		'channel': ch_meta,
		'videos':  full_videos,
	}
	out_path = RAW_DIR / f'{handle.lstrip("@")}.json'
	out_path.write_text(json.dumps(output, indent=2, ensure_ascii=False, default=str), encoding='utf-8')
	print(f'  → saved {len(full_videos)} videos to {out_path}', file=sys.stderr, flush=True)

	return {
		'handle':   handle,
		'quota':    quota,
		'total':    len(videos),
		'filtered': len(filtered),
		'fetched':  len(full_videos),
		'output':   str(out_path),
	}


def main() -> None:
	RAW_DIR.mkdir(parents=True, exist_ok=True)
	summary = []
	for handle, quota in CHANNELS.items():
		summary.append(process_channel(handle, quota))

	# Write summary to known location.
	summary_path = RAW_DIR / '_summary.json'
	summary_path.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding='utf-8')
	print(f'\n=== DONE — summary at {summary_path} ===', file=sys.stderr, flush=True)


if __name__ == '__main__':
	main()
