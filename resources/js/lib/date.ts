export function fromNow(date: Date) {
	const now = new Date();
	const diff = (date.getTime() - now.getTime()) / 1000; // seconds
	const absDiff = Math.abs(diff);

	let unit: Intl.RelativeTimeFormatUnit = "second";
	let value = diff;

	if (absDiff > 60 && absDiff < 3600) {
		unit = "minute";
		value = diff / 60;
	} else if (absDiff >= 3600 && absDiff < 86400) {
		unit = "hour";
		value = diff / 3600;
	} else if (absDiff >= 86400) {
		unit = "day";
		value = diff / 86400;
	} else if (absDiff >= 604800) {
		unit = "week";
		value = diff / 604800;
	} else if (absDiff >= 2419200) {
		unit = "month";
		value = diff / 2419200;
	}

	return new Intl.RelativeTimeFormat("en", { numeric: "auto" }).format(
		Math.round(value),
		unit
	);
}
