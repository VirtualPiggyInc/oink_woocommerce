<?php
/**
 * @package VirtualPiggy.Services.Implementations
 */
class MockCartService implements ICartService{
    
    
    public function GetCurrentCart(){
        
        $cart = new dtoCart();
        $cart->Currency = "USD";
        $cart->Total = 10.00;
        $cart->ShippmentTotal = 0;
        $cart->Tax = 0;
        $cart->Cost = 0;
        $cart->Discount = 0;
        
        $address = new dtoAddress();
        $address->Address = "134 Easy st";
        $address->Zip = "91367";
        $address->State = "CA";
        $address->City = "Woodland Hills";
        $address->Country = "US";
        $address->Phone = "555.555.5555";
        $address->ParentName = "Harry Potter";
        $address->ChildName = "Aldus Potter";
        $cart->ShipmentAddress = $address;

        $item = new dtoCartItem();
        $item->Total = 4.50;
        $item->Name = "Mock item";
        $item->Description = "Mock description";
        $item->Price = 4.50;
        $item->Quantity = 1;
        $cart->AddItem(($item));
        
        $item_2 = new dtoCartItem();
        $item_2->Total = 5.50;
        $item_2->Name = "Mock item number 2";
        $item_2->Description = "Mock description number 2";
        $item_2->Price = 5.50;
        $item_2->Quantity = 1;
        $cart->AddItem(($item_2));        
        return $cart;
    }
    
    public function GetCurrentSubscription(){
        
        $subscription = new dtoSubscription();
        $subscription->Currency = 'USD';
        $subscription->ExpirationDate = '2012-12-05';
        $subscription->Period = 'Weekly';
        $subscription->Total = 12;
        return $subscription;
    }
    public function GetCurrentSubscriptTransaction(){
        $cart = new dtoCart();
        $cart->Currency = "USD";
        $cart->Total = 12.00;
        $cart->ShippmentTotal = 0;
        $cart->Tax = 0;
        $cart->Cost = 0;
        $cart->Discount = 0;
        
        $address = new dtoAddress();
        $address->Address = "134 Easy st";
        $address->Zip = "91367";
        $address->State = "CA";
        $address->City = "Woodland Hills";
        $address->Country = "US";
        $address->Phone = "555.555.5555";
        $address->ParentName = "Harry Potter";
        $address->ChildName = "Aldus Potter";
        $cart->ShipmentAddress = $address;

        $item = new dtoCartItem();
        $item->Total = 12.00;
        $item->Name = "Monthly Subscription";
        $item->Description = "Monthly subscription to a great site.";
        $item->Price = 12.00;
        $item->Quantity = 1;
        $cart->AddItem(($item));

        return $cart;        
    }
    public function GetCurrentWishlist(){
        $wishlist = new dtoWishlist();

        $item = new dtoWishlistItem();
        $item->Identifier = "123792359723";
        $item->Description = "The Get Up Kids: Band Camp Pullover Hoodie";
        $item->Url = "http://ecommercesite.com/product/hoodie-192.html";
        $wishlist->AddItem(($item));

        return $wishlist;
    }
    
    
}


?>
