<script lang="ts">
	import * as AlertDialog from "@/shadcn/ui/alert-dialog";
	import { buttonVariants } from "@/shadcn/ui/button";
	import FormErrors from "@/components/FormErrors.svelte";
	import LoadingButton from "@/components/LoadingButton.svelte";
	import { Label } from "@/shadcn/ui/label";
	import { Input } from "@/shadcn/ui/input";
	import { useForm, useInertia } from "@eslym/svelte5-inertia";

	const id = $props.id();

	let { open = $bindable(false) }: { open?: boolean } = $props();

	const inertia = useInertia();
	const form = useForm({ new_password: "", repeat_password: "" });
</script>

<AlertDialog.Root
	bind:open
	onOpenChange={(val) => {
		if (val) {
			form.reset();
			form.errors = {};
		}
	}}
>
	<AlertDialog.Content class="@container">
		<AlertDialog.Header>
			<AlertDialog.Title>Update Password</AlertDialog.Title>
			<AlertDialog.Description>
				Please update your password to continue using the application.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<form
			id="{id}-update-password"
			action="/update-password"
			method="post"
			use:form.action={{
				replace: true,
				preserveState: true,
				preserveScroll: true
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
		</form>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>
				{#snippet child({ props })}
					<a
						{...props}
						href="/logout"
						class={buttonVariants({ variant: "outline" })}
						use:inertia.link
					>
						Logout
					</a>
				{/snippet}
			</AlertDialog.Cancel>
			<AlertDialog.Action>
				{#snippet child({ props })}
					<LoadingButton
						{...props}
						type="submit"
						form="{id}-update-password"
						loading={form.processing}
					>
						Save
					</LoadingButton>
				{/snippet}
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
