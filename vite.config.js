import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import tailwindcss from "@tailwindcss/vite";
//@ts-ignore
import { resolve, join } from "node:path";
import { hash } from "node:crypto";
import fs from "node:fs";

/**
 * @returns {import("vite").Plugin}
 */
function injectManifest() {
	let isSSR = false;

	return {
		name: "post-build-manifest",
		apply: "build",
		configResolved(config) {
			isSSR = !!config.build.ssr;
		},
		async writeBundle(opts) {
			if (isSSR) {
				this.info("Skipping service worker generation for SSR build.");
				return;
			}
			const manifestPath = join(opts.dir, "manifest.json");
			const swPath = join(opts.dir, "service-worker.js");
			const manifestContent = await fs.promises.readFile(manifestPath);
			const manifest = JSON.parse(manifestContent.toString("utf-8"));
			const swContent = await fs.promises.readFile(swPath);
			const sw = swContent.toString("utf-8");
			const manifestHash = hash(
				"sha1",
				Buffer.concat([manifestContent, swContent]),
				"hex"
			);
			const assetsArray = Object.values(manifest)
				.map((v) => `/build/${v.file}`)
				.filter((v) => v !== "/build/service-worker.js");
			const out = sw
				.replace("__SW_VERSION", JSON.stringify(manifestHash))
				.replace("__SW_ASSETS", JSON.stringify(assetsArray));
			await fs.promises.writeFile(
				join(opts.dir, "../service-worker.js"),
				out,
				"utf-8"
			);
			await fs.promises.unlink(swPath);
			this.info("Service worker generated with manifest data.");
		}
	};
}

export default defineConfig({
	plugins: [
		svelte({}),
		laravel({
			input: [
				"resources/css/app.css",
				"resources/js/app.ts",
				"resources/js/service-worker.ts"
			],
			ssr: ["resources/js/ssr/index.ts", "resources/js/ssr/worker.ts"],
			refresh: true
		}),
		tailwindcss(),
		injectManifest()
	],
	ssr: {
		noExternal: true
	},
	resolve: {
		alias: {
			"@": resolve("resources/js")
		}
	},
	server: {
		hmr: {
			host: "localhost"
		}
	},
	build: {
		rollupOptions: {
			output: {
				entryFileNames: (chunkInfo) => {
					if (chunkInfo.name === "service-worker") {
						return "service-worker.js";
					}
					return "assets/[name]-[hash].js";
				}
			}
		}
	}
});
