<script lang="ts">
	import * as Dialog from "@/shadcn/ui/dialog";
	import FormErrors from "@/components/FormErrors.svelte";
	import LoadingButton from "@/components/LoadingButton.svelte";
	import { Label } from "@/shadcn/ui/label";
	import { Input } from "@/shadcn/ui/input";
	import { useForm } from "@eslym/svelte5-inertia";
	import { onDestroy, type Snippet } from "svelte";
	import { lockable } from "@eslym/svelte5-utils";

	const id = $props.id();

	let { children = undefined }: { children?: Snippet<[typeof Dialog]> } =
		$props();

	const form = useForm({
		new_password: "",
		repeat_password: "",
		current_password: ""
	});

	const open = lockable(() => form.processing, false);

	onDestroy(() => open.force(false));
</script>

<Dialog.Root
	bind:open={open.value}
	onOpenChange={(val) => {
		if (val) {
			form.reset();
			form.errors = {};
		}
	}}
>
	{@render children?.(Dialog)}
	<Dialog.Content class="@container">
		<Dialog.Header>
			<Dialog.Title>Update Password</Dialog.Title>
			<Dialog.Description>
				Verify your current password and set a new one.
			</Dialog.Description>
		</Dialog.Header>
		<form
			id="{id}-update-password"
			action="/update-password"
			method="post"
			use:form.action={{
				replace: true,
				preserveState: true,
				preserveScroll: true,
				onSuccess() {
					open.force(false);
				}
			}}
			class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-4 @max-xs:grid-cols-1"
		>
			<div
				class="col-span-2 grid grid-cols-subgrid gap-x-6 gap-y-2 @max-xs:col-span-1"
			>
				<Label
					for="{id}-password"
					class="justify-end @max-xs:justify-start"
				>
					New Password
				</Label>
				<Input
					id="{id}-new-password"
					type="password"
					bind:value={form.data.new_password}
					disabled={form.processing}
				/>
				<FormErrors
					class="col-start-2 @max-xs:col-start-1"
					errors={form.errors.new_password}
				/>
			</div>
			<div
				class="col-span-2 grid grid-cols-subgrid gap-x-6 gap-y-2 @max-xs:col-span-1"
			>
				<Label
					for="{id}-repeat-password"
					class="justify-end @max-xs:justify-start"
				>
					Repeat Password
				</Label>
				<Input
					id="{id}-repeat-password"
					type="password"
					bind:value={form.data.repeat_password}
					disabled={form.processing}
				/>
				<FormErrors
					class="col-start-2 @max-xs:col-start-1"
					errors={form.errors.repeat_password}
				/>
			</div>
			<div
				class="col-span-2 mt-6 grid grid-cols-subgrid gap-x-6 gap-y-2 @max-xs:col-span-1"
			>
				<Label
					for="{id}-current-password"
					class="justify-end @max-xs:justify-start"
				>
					Current Password
				</Label>
				<Input
					id="{id}-current-password"
					type="password"
					bind:value={form.data.current_password}
					disabled={form.processing}
				/>
				<FormErrors
					class="col-start-2 @max-xs:col-start-1"
					errors={form.errors.current_password}
				/>
			</div>
		</form>
		<Dialog.Footer>
			<LoadingButton
				type="submit"
				form="{id}-update-password"
				loading={form.processing}
			>
				Update
			</LoadingButton>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
