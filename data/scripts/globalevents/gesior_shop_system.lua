local messageType = MESSAGE_EVENT_ADVANCE or MESSAGE_STATUS_CONSOLE_ORANGE

local function sendShopDeliveryMessage(player, offerName)
	player:sendTextMessage(messageType, string.format("Your webshop purchase has been delivered: %s.", offerName))
end

local function getPendingOrders(playerName)
	local query = "SELECT id, name, param1, param2, param3, param4, param5, param6 FROM z_ots_comunication"
	if playerName then
		query = query .. " WHERE name = " .. db.escapeString(playerName)
	end

	local resultId = db.storeQuery(query)
	if not resultId then
		return {}
	end

	local orders = {}
	repeat
		local param3 = result.getString(resultId, "param3")
		local param4 = result.getString(resultId, "param4")
		table.insert(orders, {
			id = result.getNumber(resultId, "id"),
			name = result.getString(resultId, "name"),
			param1 = result.getNumber(resultId, "param1"),
			param2 = result.getNumber(resultId, "param2"),
			param3 = tonumber(param3) or 0,
			param4 = tonumber(param4) or 0,
			param5 = result.getString(resultId, "param5"),
			param6 = result.getString(resultId, "param6"),
		})
	until not result.next(resultId)

	result.free(resultId)
	return orders
end

local function completeOrder(orderId)
	db.query("DELETE FROM `z_ots_comunication` WHERE `id` = " .. orderId)
	db.query("UPDATE `z_shop_history` SET `trans_state` = 'realized', `trans_real` = " .. os.time() .. " WHERE `comunication_id` = " .. orderId)
end

local function deliverItem(player, order)
	local itemType = ItemType(order.param1)
	if not itemType then
		logger.error("[gesior_shop_system] Invalid item id {} on order {}.", order.param1, order.id)
		return false
	end

	local canReceive, errorMessage = player:canReceiveStoreItems(order.param1, order.param2)
	if not canReceive then
		if errorMessage then
			player:sendTextMessage(MESSAGE_FAILURE, errorMessage)
		end
		logger.info("[gesior_shop_system] Order {} for '{}' is waiting for free space in the store inbox.", order.id, order.name)
		return false
	end

	local inbox = player:getStoreInbox()
	if not inbox then
		logger.error("[gesior_shop_system] Player '{}' has no store inbox for order {}.", order.name, order.id)
		return false
	end

	local remaining = order.param2
	local stackSize = itemType:isStackable() and itemType:getStackSize() or 1

	while remaining > 0 do
		local countToAdd = itemType:isStackable() and math.min(remaining, stackSize) or 1
		local item = Game.createItem(order.param1, countToAdd)
		if not item then
			logger.error("[gesior_shop_system] Failed to create item {} x{} for '{}' on order {}.", order.param1, countToAdd, order.name, order.id)
			return false
		end

		item:setAttribute(ITEM_ATTRIBUTE_STORE, systemTime())
		local inboxItem = inbox:addItemEx(item, INDEX_WHEREEVER, FLAG_NOLIMIT)
		if inboxItem ~= RETURNVALUE_NOERROR then
			logger.error("[gesior_shop_system] Failed to add item {} x{} to '{}' store inbox for order {}. Return value: {}.", order.param1, countToAdd, order.name, order.id, inboxItem)
			item:remove()
			return false
		end

		remaining = remaining - countToAdd
	end

	player:sendUpdateContainer(inbox)
	sendShopDeliveryMessage(player, order.param6)
	return true
end

local function deliverAddon(player, order)
	player:addOutfitAddon(order.param1, order.param3)
	player:addOutfitAddon(order.param2, order.param4)
	player:getPosition():sendMagicEffect(CONST_ME_GIFT_WRAPS)
	sendShopDeliveryMessage(player, order.param6)
	return true
end

local function deliverMount(player, order)
	player:addMount(order.param1)
	player:getPosition():sendMagicEffect(CONST_ME_GIFT_WRAPS)
	sendShopDeliveryMessage(player, order.param6)
	return true
end

local function processOrderForPlayer(player, order)
	local delivered = false

	if order.param5 == "item" then
		delivered = deliverItem(player, order)
	elseif order.param5 == "addon" then
		delivered = deliverAddon(player, order)
	elseif order.param5 == "mount" then
		delivered = deliverMount(player, order)
	else
		logger.warn("[gesior_shop_system] Unsupported delivery type '{}' for '{}'. Keeping order {} pending.", order.param5, order.name, order.id)
		return false
	end

	if not delivered then
		return false
	end

	completeOrder(order.id)
	logger.info("[gesior_shop_system] Delivered order {} to '{}' (type {}, item {}, amount {}).", order.id, order.name, order.param5, order.param1, order.param2)
	return true
end

local function processOrdersForPlayer(player)
	local orders = getPendingOrders(player:getName())
	if #orders == 0 then
		return 0, 0
	end

	local delivered, pending = 0, 0
	for _, order in ipairs(orders) do
		if processOrderForPlayer(player, order) then
			delivered = delivered + 1
		else
			pending = pending + 1
		end
	end

	return delivered, pending
end

local gesiorShopSystemThink = GlobalEvent("gesior.shop.onThink")

function gesiorShopSystemThink.onThink(interval)
	local orders = getPendingOrders()
	if #orders == 0 then
		return true
	end

	local processedPlayers = {}
	local delivered, pending = 0, 0

	for _, order in ipairs(orders) do
		if not processedPlayers[order.name] then
			processedPlayers[order.name] = true
			local player = Player(order.name)
			if player then
				local deliveredNow, pendingNow = processOrdersForPlayer(player)
				delivered = delivered + deliveredNow
				pending = pending + pendingNow
			else
				pending = pending + 1
			end
		end
	end

	logger.info("Shopsystem processed queue: {} delivered, {} pending.", delivered, pending)
	return true
end

gesiorShopSystemThink:interval(30 * 1000)
gesiorShopSystemThink:register()

local gesiorShopSystemLogin = CreatureEvent("gesior.shop.onLogin")

function gesiorShopSystemLogin.onLogin(player)
	addEvent(function(playerId)
		local onlinePlayer = Player(playerId)
		if not onlinePlayer then
			return
		end

		local delivered, pending = processOrdersForPlayer(onlinePlayer)
		if delivered > 0 or pending > 0 then
			logger.info("[gesior_shop_system] Login processing for '{}': {} delivered, {} pending.", onlinePlayer:getName(), delivered, pending)
		end
	end, 1000, player:getId())

	return true
end

gesiorShopSystemLogin:register()
