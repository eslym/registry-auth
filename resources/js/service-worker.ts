/// <reference no-default-lib="true"/>
/// <reference lib="esnext" />
/// <reference lib="webworker" />

declare var __SW_VERSION: string;
declare var __SW_ASSETS: string[];

const sw = self as any as ServiceWorkerGlobalScope & typeof globalThis;

const PRECACHE_NAME = "precache-" + __SW_VERSION;
const ASSET_CACHE_NAME = "assets";
const ASSETS = new Set([
	...__SW_ASSETS,
	"/favicon.ico",
	"/favicon.svg",
	"/favicon-512w.png"
]);

async function install() {
	sw.skipWaiting();
	if (await caches.has(PRECACHE_NAME)) return;
	const cache = await caches.open(PRECACHE_NAME);
	await cache.addAll(
		Array.from(ASSETS).map((v) => new Request(v, { cache: "reload" }))
	);
}

async function activate() {
	const keys = await caches.keys();
	for (const key of keys) {
		if (key !== PRECACHE_NAME && key !== ASSET_CACHE_NAME) {
			await caches.delete(key);
		}
	}
}

sw.addEventListener("install", (event) => {
	event.waitUntil(install());
});

sw.addEventListener("activate", (event) => {
	event.waitUntil(activate());
});

const METHODS = new Set(["GET", "HEAD"]);
const CACHEABLE = new Set(["image", "font", "style"]);

sw.addEventListener("fetch", (event) => {
	const url = new URL(event.request.url);
	if (!METHODS.has(event.request.method)) return;
	if (url.origin === self.location.origin && ASSETS.has(url.pathname)) {
		return event.respondWith(cacheFirst(event.request));
	}
	if (CACHEABLE.has(event.request.destination)) {
		return event.respondWith(networkFirst(event.request, ASSET_CACHE_NAME));
	}
});

async function networkFirst(request: Request | string, cn = PRECACHE_NAME) {
	const cache = await caches.open(cn);
	try {
		const response = await fetch(request);
		if (response.status >= 2000 && response.status < 300) {
			await cache.put(request, response.clone());
		}
		return response;
	} catch (err) {
		const cached = await cache.match(request);
		if (cached) return cached;
		throw err;
	}
}

async function cacheFirst(request: Request | string, cn = PRECACHE_NAME) {
	const cache = await caches.open(cn);
	const cached = await cache.match(request);
	if (cached) return cached;
	const response = await fetch(request, {
		cache: "reload"
	});
	if (response.status >= 200 && response.status < 300) {
		await cache.put(request, response.clone());
	}
	return response;
}
