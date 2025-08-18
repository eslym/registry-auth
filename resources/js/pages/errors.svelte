<script lang="ts">
	import { Config } from "@/lib/config";

	let { status }: { status: number } = $props();

	const config = Config.get();

	function getMessage(status: number): string {
		switch (status) {
			case 401:
				return "Unauthorized";
			case 403:
				return "Forbidden";
			case 404:
				return "Not Found";
			case 405:
				return "Method Not Allowed";
			case 419:
				return "Page Expired";
			case 429:
				return "Too Many Requests";
			case 500:
				return "Internal Server Error";
			case 503:
				return "Service Unavailable";
			default:
				return "An error occurred";
		}
	}

	let message = $derived(getMessage(status));
</script>

<svelte:head>
	<title>{status} {message} | {config.appName}</title>
</svelte:head>

<div
	class="items-top bg-background relative flex min-h-screen justify-center sm:items-center sm:pt-0"
>
	<div class="mx-auto max-w-xl sm:px-6 lg:px-8">
		<div class="flex items-center pt-8 sm:justify-start sm:pt-0">
			<div
				class="text-foreground border-border border-r px-4 text-lg tracking-wider"
			>
				{status}
			</div>
			<div
				class="text-muted-foreground ml-4 text-lg tracking-wider uppercase"
			>
				{message}
			</div>
		</div>
	</div>
</div>
