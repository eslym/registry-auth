<script lang="ts">
	import { Config, configProxy } from "@/lib/config";
	import { useInertia } from "@eslym/svelte5-inertia";
	import { toast } from "svelte-sonner";
	import { theme } from "@/lib/theme.svelte";
	import * as AlertDialog from "@/shadcn/ui/alert-dialog";
	import type { Snippet } from "svelte";
	import { Toaster } from "@/shadcn/ui/sonner";
	import Cookies from "js-cookie";

	type AlertData = {
		title: string;
		message: string;
		close?: string;
	};

	type ToastData = {
		type: "success" | "error" | "info" | "warning";
		title: string;
		description?: string;
	};

	const inertia = useInertia();

	let {
		children,
		config,
		_toast,
		_alert
	}: {
		children: Snippet;
		config?: Config;
		_toast?: ToastData;
		_alert?: AlertData;
	} = $props();

	let cfg = $state(config);

	let alert: AlertData | undefined = $state(undefined);

	Config.set(configProxy(() => cfg!));

	$effect(() => {
		if (config) cfg = config;
	});

	$effect(() => {
		if (_alert || _toast) {
			inertia.router.replace({
				preserveState: true,
				preserveScroll: true,
				props(props) {
					if (_toast) {
						toast[_toast.type](_toast.title, {
							description: _toast.description
						});
						delete props._toast;
					}
					if (_alert) {
						const data = _alert;
						setTimeout(() => (alert = data), 100);
						delete props._alert;
					}
					return props;
				}
			});
		}
	});

	if (!import.meta.env.SSR) {
		$effect.pre(() => {
			if (theme.current === "dark") {
				document.documentElement.classList.add("dark");
			} else {
				document.documentElement.classList.remove("dark");
			}
			Cookies.set("theme", theme.current, {
				expires: new Date(Date.now() + 1000 * 60 * 60 * 24 * 365 * 10),
				sameSite: "Lax"
			});
		});
	}

	$inspect(inertia.page);
</script>

{@render children?.()}

<Toaster />

{#if alert}
	<AlertDialog.Root
		open
		onOpenChangeComplete={(open) => {
			if (!open) alert = undefined;
		}}
	>
		<AlertDialog.Content>
			<AlertDialog.Header>
				<AlertDialog.Title>
					{alert.title}
				</AlertDialog.Title>
				<AlertDialog.Description>
					{alert.message}
				</AlertDialog.Description>
			</AlertDialog.Header>
			<AlertDialog.Footer>
				<AlertDialog.Cancel>
					{alert.close || "Close"}
				</AlertDialog.Cancel>
			</AlertDialog.Footer>
		</AlertDialog.Content>
	</AlertDialog.Root>
{/if}
