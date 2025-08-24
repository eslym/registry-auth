<script lang="ts">
	import * as Sheet from "@/shadcn/ui/sheet";
	import { onDestroy, type Snippet } from "svelte";
	import { useFormDerived, useInertia } from "@eslym/svelte5-inertia";
	import { Label } from "@/shadcn/ui/label";
	import { Input } from "@/shadcn/ui/input";
	import FormErrors from "@/components/FormErrors.svelte";
	import { ScrollArea } from "@/shadcn/ui/scroll-area";
	import AccessControlEditor from "@/components/AccessControlEditor.svelte";
	import LoadingButton from "@/components/LoadingButton.svelte";

	const id = $props.id();
	const inertia = useInertia();

	let {
		open = $bindable(false),
		children = undefined,
		group,
		editable = true,
		onclose = undefined,
		...opts
	}: {
		open?: boolean;
		children?: Snippet<[typeof Sheet]>;
		group: Partial<Model.Group>;
		editable?: boolean;
		replace?: boolean;
		preserveState?: boolean;
		preserveScroll?: boolean;
		onclose?: () => void;
	} = $props();

	const form = useFormDerived(() => ({
		name: group.name ?? "",
		access_controls:
			group.access_controls ?? ([] as Partial<Model.AccessControl>[])
	}));

	let action = $derived.by(() => {
		const url = new URL(
			inertia.page.url,
			import.meta.env.SSR ? inertia.page.url : window.location.origin
		);
		url.pathname = `/groups/${group.id ?? ""}`;
		return url.toString();
	});

	onDestroy(() => (open = false));
</script>

<Sheet.Root
	bind:open={
		() => open,
		(val) => {
			if (!form.processing) open = val;
		}
	}
	onOpenChange={(val) => {
		if (val) {
			form.reset();
			form.errors = {};
		} else onclose?.();
	}}
>
	{@render children?.(Sheet)}
	<Sheet.Content side="right">
		<Sheet.Header>
			<Sheet.Title>
				{#if !group.id}
					Create Group
				{:else}
					View Group
				{/if}
			</Sheet.Title>
		</Sheet.Header>
		<ScrollArea class="h-0 flex-1 border-y">
			<form
				{id}
				class="grid auto-rows-min gap-4 p-4"
				method="post"
				{action}
				use:form.action={{
					...opts,
					onSuccess() {
						open = false;
						onclose?.();
					}
				}}
			>
				<div class="grid gap-2">
					<Label for="{id}-name">Name</Label>
					<Input
						id="{id}-name"
						name="name"
						type="text"
						bind:value={form.data.name}
						readonly={!editable}
					/>
					<FormErrors errors={form.errors.name} />
				</div>
				<div class="grid gap-2">
					<Label>Access Controls</Label>
					<AccessControlEditor
						{form}
						{editable}
						class="h-120 rounded-md border"
					/>
				</div>
			</form>
		</ScrollArea>
		{#if editable}
			<Sheet.Footer>
				<LoadingButton
					form={id}
					type="submit"
					loading={form.processing}
				>
					{group.id ? "Save" : "Create"}
				</LoadingButton>
			</Sheet.Footer>
		{/if}
	</Sheet.Content>
</Sheet.Root>
