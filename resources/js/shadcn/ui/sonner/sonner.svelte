<script lang="ts">
	import {
		Toaster as Sonner,
		type ToasterProps as SonnerProps
	} from "svelte-sonner";
	import { theme } from "@/lib/theme.svelte";

	let { ...restProps }: SonnerProps = $props();
</script>

<Sonner
	theme={theme.current}
	class="toaster group"
	style="--normal-bg: var(--color-popover); --normal-text: var(--color-popover-foreground); --normal-border: var(--color-border);"
	{...restProps}
/>
