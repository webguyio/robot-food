<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:site="https://robotfood.me/">
	<xsl:output method="html" encoding="UTF-8" indent="yes"/>
	<xsl:template match="/">
		<html lang="en">
			<head>
				<meta charset="UTF-8"/>
				<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
				<title>Sitemap</title>
				<style><![CDATA[
					*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
					body {
						font-family: system-ui, sans-serif;
						font-size: 14px;
						line-height: 1.6;
						padding: 40px 24px;
						background: #fff;
						color: #1a1a1a;
					}
					h1 {
						font-size: 20px;
						font-weight: 600;
						margin-bottom: 4px;
					}
					p {
						color: #666;
						margin-bottom: 24px;
					}
					table {
						width: 100%;
						border-collapse: collapse;
					}
					th {
						text-align: left;
						padding: 8px 12px;
						font-weight: 600;
						border-bottom: 2px solid #e5e5e5;
						color: #444;
					}
					td {
						padding: 8px 12px;
						border-bottom: 1px solid #f0f0f0;
					}
					a {
						color: #0070f3;
						text-decoration: none;
					}
					a:hover {
						text-decoration: underline;
					}
					@media (prefers-color-scheme: dark) {
						body { background: #111; color: #eee; }
						p { color: #999; }
						th { border-bottom-color: #333; color: #aaa; }
						td { border-bottom-color: #222; }
						a { color: #60a5fa; }
					}
				]]></style>
			</head>
			<body>
				<h1><xsl:value-of select="sm:urlset/site:name"/> Sitemap</h1>
				<p><xsl:value-of select="count(sm:urlset/sm:url)"/> URL<xsl:if test="count(sm:urlset/sm:url) != 1">s</xsl:if></p>
				<table>
					<thead>
						<tr>
							<th>URL</th>
							<xsl:if test="sm:urlset/sm:url/sm:lastmod"><th>Last Modified</th></xsl:if>
						</tr>
					</thead>
					<tbody>
						<xsl:for-each select="sm:urlset/sm:url">
							<tr>
								<td><a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a></td>
								<xsl:if test="sm:lastmod"><td><xsl:value-of select="sm:lastmod"/></td></xsl:if>
							</tr>
						</xsl:for-each>
					</tbody>
				</table>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>