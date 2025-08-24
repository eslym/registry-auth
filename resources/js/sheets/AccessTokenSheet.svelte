<script lang="ts">
	import * as Sheet from "@/shadcn/ui/sheet";
	import * as Popover from "@/shadcn/ui/popover";
	import { Calendar } from "@/shadcn/ui/calendar";
	import { onDestroy, type Snippet } from "svelte";
	import { useFormDerived, useInertia } from "@eslym/svelte5-inertia";
	import { Label } from "@/shadcn/ui/label";
	import { Input } from "@/shadcn/ui/input";
	import FormErrors from "@/components/FormErrors.svelte";
	import { ScrollArea } from "@/shadcn/ui/scroll-area";
	import AccessControlEditor from "@/components/AccessControlEditor.svelte";
	import LoadingButton from "@/components/LoadingButton.svelte";
	import { CalendarDate, today } from "@internationalized/date";
	import { Button, buttonVariants } from "@/shadcn/ui/button";
	import { CalendarIcon } from "@lucide/svelte";
	import { Config } from "@/lib/config";

	const id = $props.id();
	const inertia = useInertia();

	let {
		open = $bindable(false),
		children = undefined,
		token,
		editable = true,
		onclose = undefined,
		...opts
	}: {
		open?: boolean;
		children?: Snippet<[typeof Sheet]>;
		token: Partial<Model.AccessToken>;
		editable?: boolean;
		replace?: boolean;
		preserveState?: boolean;
		preserveScroll?: boolean;
		onclose?: () => void;
	} = $props();

	const config = Config.get();

	const form = useFormDerived(() => {
		let expired_at: CalendarDate | undefined = undefined;
		if (token.expired_at) {
			const date = new Date(token.expired_at);
			expired_at = new CalendarDate(
				date.getFullYear(),
				date.getMonth() + 1,
				date.getDate()
			);
		}
		return {
			description: token.description ?? "",
			expired_at,
			access_controls:
				token.access_controls ?? ([] as Partial<Model.AccessControl>[])
		};
	}).transform(({ expired_at, ...data }) => ({
		...data,
		expired_at: expired_at?.toDate(config.timezone)
	}));

	let action = $derived.by(() => {
		const url = new URL(
			inertia.page.url,
			import.meta.env.SSR ? undefined : window.location.origin
		);
		url.pathname = `/profile/${token.id ?? ""}`;
		return url.toString();
	});

	let df = $derived.by(() => {
		return new Intl.DateTimeFormat("en-US", {
			year: "numeric",
			month: "short",
			day: "numeric",
			timeZone: config.timezone
		});
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
				{#if !token.id}
					Create Access Token
				{:else}
					View Access Token
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
					<Label for="{id}-description">Description</Label>
					<Input
						id="{id}-description"
						name="description"
						type="text"
						bind:value={form.data.description}
						readonly={!editable}
					/>
					<FormErrors errors={form.errors.description} />
				</div>
				<div class="grid gap-2">
					<Label for="{id}-expired_at">Expired At</Label>
					{#snippet renderCalendar(value: CalendarDate | undefined)}
						<CalendarIcon />
						{#if value}
							{df.format(value.toDate(config.timezone))}
						{:else}
							Never
						{/if}
					{/snippet}
					{#if editable}
						<Popover.Root>
							<Popover.Trigger
								class={buttonVariants({
									variant: "outline",
									class: [
										"w-full justify-start text-left font-normal",
										!form.data.expired_at &&
											"text-muted-foreground"
									]
								})}
							>
								{@render renderCalendar(form.data.expired_at)}
							</Popover.Trigger>
							<Popover.Content class="w-auto p-0">
								<Calendar
									type="single"
									captionLayout="dropdown"
									bind:value={form.data.expired_at}
									minValue={today(config.timezone).add({
										days: 1
									})}
								/>
							</Popover.Content>
						</Popover.Root>
					{:else}
						<Button
							variant="outline"
							class={[
								"w-full justify-start text-left font-normal",
								!form.data.expired_at && "text-muted-foreground"
							]}
						>
							{@render renderCalendar(form.data.expired_at)}
						</Button>
					{/if}
					<FormErrors errors={form.errors.expired_at} />
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
					{token.id ? "Save" : "Create"}
				</LoadingButton>
			</Sheet.Footer>
		{/if}
	</Sheet.Content>
</Sheet.Root>
