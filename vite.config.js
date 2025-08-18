import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import tailwindcss from "@tailwindcss/vite";
//@ts-ignore
import { resolve } from "node:path";

export default defineConfig({
	plugins: [
		svelte({}),
		laravel({
			input: ["resources/css/app.css", "resources/js/app.ts"],
			ssr: ["resources/js/ssr/index.ts", "resources/js/ssr/worker.ts"],
			refresh: true
		}),
		tailwindcss()
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
	}
});
