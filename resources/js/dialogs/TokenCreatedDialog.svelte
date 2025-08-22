<script lang="ts">
	import * as AlertDialog from "@/shadcn/ui/alert-dialog";
	import { toast } from "svelte-sonner";

	let { token, open = $bindable() }: { token: string | null; open: boolean } =
		$props();

	let failed = $state(false);
</script>

<AlertDialog.Root bind:open>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Access Token Created</AlertDialog.Title>
			<AlertDialog.Description>
				Your access token has been created successfully. Please copy it
				now, as you won't be able to see it again.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<pre
			class="bg-card my-4 w-full rounded-sm border px-4 py-2 text-center font-mono max-sm:text-sm"><code
				>{token}</code
			></pre>
		<AlertDialog.Footer>
			<AlertDialog.Action
				onclick={async () => {
					if (failed) {
						failed = false;
						open = false;
						return;
					}
					try {
						await navigator.clipboard.writeText(token ?? "");
						open = false;
						toast.success("Copied to clipboard", {
							description:
								"You can now paste it wherever you need."
						});
					} catch (e) {
						failed = true;
						toast.error("Failed to copy to clipboard", {
							description:
								"Please make sure you have note down the token manually."
						});
					}
				}}
			>
				Copy and Close
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
