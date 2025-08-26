<script lang="ts">
	import * as AlertDialog from "@/shadcn/ui/alert-dialog";
	import { onDestroy, type Snippet } from "svelte";
	import { useForm } from "@eslym/svelte5-inertia";
	import LoadingButton from "@/components/LoadingButton.svelte";

	let {
		url,
		open = $bindable(false),
		children = undefined,
		title = "Confirm Delete",
		description = "Are you sure you want to delete this item? This action cannot be undone.",
		...opts
	}: {
		url: string | URL;
		open?: boolean;
		children?: Snippet<[typeof AlertDialog]>;
		title?: string;
		description?: string;
		replace?: boolean;
		preserveState?: boolean;
		preserveScroll?: boolean;
	} = $props();

	const form = useForm({});

	onDestroy(() => (open = false));
</script>

<AlertDialog.Root
	bind:open={
		() => open,
		(val) => {
			if (!form.processing) open = val;
		}
	}
>
	{@render children?.(AlertDialog)}
	<AlertDialog.Content>
		<form
			action={url.toString()}
			use:form.action={{
				...opts,
				method: "delete",
				onFinish() {
					open = false;
				}
			}}
			class="contents"
		>
			<AlertDialog.Header>
				<AlertDialog.Title>{title}</AlertDialog.Title>
				<AlertDialog.Description>
					{description}
				</AlertDialog.Description>
			</AlertDialog.Header>
			<AlertDialog.Footer>
				<AlertDialog.Cancel type="button" disabled={form.processing}>
					Cancel
				</AlertDialog.Cancel>
				<AlertDialog.Action>
					{#snippet child({ props: { class: _, ...props } })}
						<LoadingButton
							{...props}
							variant="destructive"
							loading={form.processing}
							onclick={() => {
								form.delete(url, opts).then(() => {
									open = false;
								});
							}}
						>
							Delete
						</LoadingButton>
					{/snippet}
				</AlertDialog.Action>
			</AlertDialog.Footer>
		</form>
	</AlertDialog.Content>
</AlertDialog.Root>
